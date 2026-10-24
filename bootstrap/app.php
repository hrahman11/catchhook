<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecureHookResponse;
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
            Route::middleware([SecureHookResponse::class, SubstituteBindings::class])
                ->group(base_path('routes/hooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Replay payloads and custom response bodies must be kept exactly as
        // written, so they skip the default input normalisation (trimming,
        // empty string to null).
        $keepsRawInput = fn (Request $request) => $request->is('requests/*/replay', 'bins/*/response');
        $middleware->trimStrings(except: [$keepsRawInput]);
        $middleware->convertEmptyStringsToNull(except: [$keepsRawInput]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'h/*') || $request->expectsJson(),
        );
    })->create();
