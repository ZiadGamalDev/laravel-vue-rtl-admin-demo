import { describe, expect, it } from 'vitest';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

/**
 * Guard against physical (left/right) Tailwind utilities that break in RTL.
 * Use logical ones instead: ms-/me-, ps-/pe-, start-/end-, text-start/end,
 * border-s/e, rounded-s/e.
 */
const PHYSICAL = /^-?(ml|mr|pl|pr|left|right|border-l|border-r|rounded-l|rounded-r|rounded-tl|rounded-tr|rounded-bl|rounded-br|text-left|text-right|float-left|float-right|scroll-ml|scroll-mr|scroll-pl|scroll-pr)(-|$)/;

function files(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) return files(path);
        return /\.(vue|ts|blade\.php)$/.test(name) && !name.endsWith('.spec.ts') ? [path] : [];
    });
}

function classTokens(source: string): string[] {
    // Static class="..." values and string literals inside :class bindings.
    const values = [...source.matchAll(/class="([^"]*)"/g)].map((m) => m[1]);
    const bound = [...source.matchAll(/:class="([^"]*)"/g)].flatMap((m) => [...m[1].matchAll(/'([^']*)'/g)].map((s) => s[1]));
    return [...values, ...bound].flatMap((v) => v.split(/\s+/)).map((t) => t.replace(/^[a-z0-9-]+:/g, ''));
}

describe('RTL-safe styling', () => {
    const sources = [...files('resources/js'), ...files('resources/views')];

    it('finds the source files', () => {
        expect(sources.length).toBeGreaterThan(20);
    });

    it.each(sources)('%s uses logical direction utilities only', (path) => {
        const offenders = classTokens(readFileSync(path, 'utf8')).filter((token) => PHYSICAL.test(token.split(':').pop() ?? ''));
        expect(offenders).toEqual([]);
    });
});
