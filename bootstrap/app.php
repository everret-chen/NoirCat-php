<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\UseSanctumGuard;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
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
        // Token first, then locale: public API endpoints must still recognise a
        // bearer token so policies can tell an author from a guest.
        $middleware->api(prepend: [UseSanctumGuard::class]);
        $middleware->api(append: [SetLocale::class]);

        // Project-specific "verified" middleware: answers with error code 1005
        // instead of the framework's generic 403 message.
        // spatie/laravel-permission does not register its own aliases, and the
        // framework's "verified" would answer with an untranslated 403.
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API failures always use the { code, message, data } envelope.
        $exceptions->render(function (Throwable $e, Request $request) {
            return app(ApiExceptionRenderer::class)->render($e, $request);
        });
    })->create();
