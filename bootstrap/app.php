<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Locale resolution for both entry points (?lang= / X-Locale / Accept-Language).
        $middleware->web(append: [SetLocale::class]);
        $middleware->api(append: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API failures always use the { code, message, data } envelope.
        $exceptions->render(function (Throwable $e, Request $request) {
            return app(ApiExceptionRenderer::class)->render($e, $request);
        });
    })->create();
