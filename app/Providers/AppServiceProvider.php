<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->freezeDemoClock();
        $this->trustConfiguredProxies();

        $tooMany = fn (Request $request, array $headers) => response()->json([
            'message' => __('auth.throttle', ['seconds' => $headers['Retry-After'] ?? 60]),
        ], 429, $headers);

        // Login: 5 attempts per minute per email + IP, and 20 per minute per IP so one
        // client cannot spray many different emails.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('email:'.Str::lower((string) $request->input('email')).'|'.$request->ip())->response($tooMany),
            Limit::perMinute(20)->by('ip:'.$request->ip())->response($tooMany),
        ]);

        // Rendering a PDF costs real CPU; previews and downloads share a modest budget.
        RateLimiter::for('documents', fn (Request $request) => Limit::perMinute(20)
            ->by('documents:'.($request->user()?->id ?? $request->ip()))->response($tooMany));

        // Sending invoices to customers: low limits, per minute and per hour.
        RateLimiter::for('invoice-email', fn (Request $request) => [
            Limit::perMinute(5)->by('invoice-email-m:'.$request->user()?->id)->response($tooMany),
            Limit::perHour(30)->by('invoice-email-h:'.$request->user()?->id)->response($tooMany),
        ]);

        // Every other API call: 120 per minute per signed-in user (or IP for guests).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));
    }

    private function trustConfiguredProxies(): void
    {
        $proxies = trim((string) config('app.trusted_proxies'));

        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }

    /**
     * When APP_DEMO_DATE is set, "now" is pinned to noon on that date so the
     * seeded data and the screenshots come out the same on every run.
     */
    private function freezeDemoClock(): void
    {
        $date = config('app.demo_date');

        if ($date && ! $this->app->runningUnitTests()) {
            Carbon::setTestNow(Carbon::parse($date)->setTime(12, 0));
        }
    }
}
