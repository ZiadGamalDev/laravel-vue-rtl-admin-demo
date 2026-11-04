// Reproducible README screenshots.
//
// 1. Builds the frontend.
// 2. Seeds a throwaway SQLite database with the clock frozen at DEMO_DATE.
// 3. Serves the app on a spare port and drives it with Playwright (1440x900).
// 4. Writes PNGs to docs/screenshots/.
//
// Usage: npm run screenshots   (needs PHP + Composer dependencies and Playwright's Chromium:
//                               npx playwright install chromium)

import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { chromium } from 'playwright';

const root = resolve(import.meta.dirname, '..');
const outDir = process.env.SCREENSHOT_OUT ? resolve(process.env.SCREENSHOT_OUT) : join(root, 'docs', 'screenshots');
const dbFile = join(root, 'database', 'screenshots.sqlite');
const port = Number(process.env.SCREENSHOT_PORT ?? 8123);
const baseUrl = `http://127.0.0.1:${port}`;
const DEMO_DATE = '2026-11-01';
const FROZEN_NOW = new Date(`${DEMO_DATE}T12:00:00Z`);
// Optional overrides (used by other image builders): output folder and pixel density.
const scale = Number(process.env.SCREENSHOT_SCALE ?? 1);
const MAX_BYTES = process.env.SCREENSHOT_OUT ? Infinity : 300 * 1024;

const env = {
    ...process.env,
    APP_ENV: 'local',
    APP_DEBUG: 'false',
    APP_URL: baseUrl,
    APP_LOCALE: 'ar',
    APP_DEMO_DATE: DEMO_DATE,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: dbFile,
    SESSION_DRIVER: 'file',
    // The server clock is frozen at DEMO_DATE, so cookie expiry dates are computed from it.
    // Stretch the lifetime past today so the browser does not drop them as already expired.
    SESSION_LIFETIME: String(Math.ceil(Math.max(0, Date.now() - FROZEN_NOW.getTime()) / 60000) + 24 * 60),
    // Per-request cache: with the clock frozen, rate-limit counters in a persistent store
    // would never expire and successive runs would end up locked out of login (429).
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'sync',
    SANCTUM_STATEFUL_DOMAINS: `127.0.0.1:${port}`,
};

function run(command, args) {
    const result = spawnSync(command, args, { cwd: root, env, stdio: 'inherit' });
    if (result.status !== 0) throw new Error(`${command} ${args.join(' ')} failed`);
}

async function waitForServer() {
    for (let i = 0; i < 100; i++) {
        try {
            if ((await fetch(`${baseUrl}/up`)).ok) return;
        } catch {
            /* not up yet */
        }
        await new Promise((r) => setTimeout(r, 200));
    }
    throw new Error('Server did not start');
}

async function settle(page) {
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(300);
}

async function shot(page, name, options = {}) {
    const path = join(outDir, `${name}.png`);
    await settle(page);
    await page.screenshot({ path, ...options });
    const size = statSync(path).size;
    console.log(`  ${name}.png  ${(size / 1024).toFixed(0)} KB`);
    if (size > MAX_BYTES) throw new Error(`${name}.png is larger than 300 KB`);
}

async function main() {
    mkdirSync(outDir, { recursive: true });

    console.log('Building frontend…');
    run('npm', ['run', 'build', '--silent']);

    console.log('Seeding a fresh demo database…');
    rmSync(dbFile, { force: true });
    run('touch', [dbFile]);
    run('php', ['artisan', 'migrate:fresh', '--seed', '--force', '--no-interaction']);

    console.log(`Starting server on ${baseUrl}…`);
    // Same as `php artisan serve`: the built-in server runs from public/ with Laravel's router script.
    const server = spawn(
        'php',
        ['-S', `127.0.0.1:${port}`, join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
        { cwd: join(root, 'public'), env, stdio: 'ignore' },
    );

    const browser = await chromium.launch();
    try {
        await waitForServer();
        const context = await browser.newContext({
            viewport: { width: 1440, height: 900 },
            deviceScaleFactor: scale,
            locale: 'ar-EG',
            timezoneId: 'UTC',
            reducedMotion: 'reduce',
        });
        const page = await context.newPage();
        await page.clock.setFixedTime(FROZEN_NOW);
        page.on('pageerror', (error) => console.error('Page error:', error.message));

        console.log('Capturing:');
        await page.goto(`${baseUrl}/login`);
        await page.waitForSelector('[data-testid=login-submit]');
        await shot(page, '01-login');

        await page.click('[data-testid=login-as-admin]');
        await page.waitForURL(`${baseUrl}/`);
        await page.waitForSelector('[data-testid=order-value-chart] canvas');
        await shot(page, '02-dashboard');

        await page.click('[data-testid=nav-customers]');
        await page.waitForSelector('[data-testid=row]');
        await shot(page, '03-customers-list');

        await page.click('[data-testid=nav-orders]');
        await page.waitForSelector('[data-testid=row]');
        await page.click('[data-testid=create]');
        await page.selectOption('select[name=customer_id]', { index: 3 });
        await page.fill('input[name="items.0.product_name"]', 'قهوة عربية ٥٠٠ جم');
        await page.fill('input[name="items.0.quantity"]', '0');
        await page.fill('input[name="items.0.unit_price"]', '18.5');
        await page.click('[data-testid=add-item]');
        await page.fill('input[name="items.1.quantity"]', '2');
        await page.click('[data-testid=form-submit]');
        await page.waitForSelector('[data-testid=field-error]');
        await shot(page, '04-order-form-validation-errors');
        await page.keyboard.press('Escape');

        await page.click('[data-testid=nav-errors]');
        await page.waitForSelector('[data-testid=row]');
        await shot(page, '05-error-log');

        await page.click('[data-testid=nav-invoices]');
        await page.waitForSelector('[data-testid=row]');
        await page.selectOption('[data-testid=status-filter]', 'paid');
        await page.waitForSelector('[data-testid=row]');
        await settle(page);
        await page.locator('[data-testid=print]').first().click();
        await page.waitForSelector('[data-testid=invoice-sheet]');
        await shot(page, '06-invoice-print');

        await page.goto(`${baseUrl}/users`);
        await page.waitForSelector('[data-testid=role-matrix]');
        await shot(page, '08-users-roles');

        await page.evaluate(() => localStorage.setItem('locale', 'en'));
        await page.goto(`${baseUrl}/`);
        await page.waitForSelector('[data-testid=order-value-chart] canvas');
        await shot(page, '07-dashboard-en');
    } finally {
        await browser.close();
        server.kill();
        rmSync(dbFile, { force: true });
    }
    console.log(`Done. Screenshots are in ${outDir}`);
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
