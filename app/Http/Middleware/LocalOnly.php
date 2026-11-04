<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Hides a route (404) unless APP_ENV=local. */
class LocalOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->environment('local'), 404);

        return $next($request);
    }
}
