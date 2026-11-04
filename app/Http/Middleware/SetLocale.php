<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the response language from the X-Locale header sent by the SPA (or ?lang=).
 * Falls back to the configured default (Arabic).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Header from the SPA's API client; ?lang= for plain links (PDF download, email preview).
        $locale = $request->header('X-Locale') ?? $request->query('lang');

        if (in_array($locale, config('app.supported_locales'), true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
