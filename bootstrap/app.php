<?php

use App\Http\Middleware\LocalOnly;
use App\Http\Middleware\SetLocale;
use App\Support\ErrorRecorder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->prepend(SetLocale::class);
        // API guests get a 401 JSON response; browser navigation goes to the SPA login page.
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
        $middleware->alias(['local.only' => LocalOnly::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Group every reported exception into the error log page. Exceptions Laravel
        // does not report (validation, 404, auth...) never reach this callback.
        $exceptions->report(function (Throwable $e) {
            app(ErrorRecorder::class)->record($e);
        });

        // Policy denials: a translated message instead of "This action is unauthorized."
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('messages.forbidden')], 403);
            }
        });
    })->create();
