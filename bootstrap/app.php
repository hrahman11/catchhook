<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(SubstituteBindings::class)
                ->group(base_path('routes/hooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Replay payloads must be forwarded exactly as written, so they skip
        // the default input normalisation (trimming, empty string to null).
        $isReplay = fn (Request $request) => $request->is('requests/*/replay');
        $middleware->trimStrings(except: [$isReplay]);
        $middleware->convertEmptyStringsToNull(except: [$isReplay]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'h/*') || $request->expectsJson(),
        );
    })->create();
