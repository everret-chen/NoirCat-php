<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes here are prefixed with /api and run through the "api" middleware
| group: the "api" rate limiter, route model binding substitution and locale
| resolution (SetLocale).
|
| Authentication uses Sanctum personal access tokens
| (Authorization: Bearer <token>). Every response uses the
| { code, message, data } envelope.
|
*/

Route::get('/health', HealthController::class)->name('api.health');

Route::prefix('auth')->name('api.auth.')->group(function (): void {
    // Public endpoints carry their own, stricter limiter (config/noircat.php).
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register');

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    Route::post('password/email', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:password_reset')
        ->name('password.email');

    Route::post('password/reset', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:password_reset')
        ->name('password.reset');


    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::post('email/verification-notification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:email_verification')
            ->name('verification.send');

        Route::get('sessions', [AuthController::class, 'sessions'])->name('sessions.index');
        Route::delete('sessions', [AuthController::class, 'destroyOtherSessions'])->name('sessions.destroyOthers');
        Route::delete('sessions/{session}', [AuthController::class, 'destroySession'])->name('sessions.destroy');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('profile.update');

        Route::post('avatar', [AuthController::class, 'uploadAvatar'])
            ->middleware('throttle:uploads')
            ->name('avatar');
    });
});

/*
|--------------------------------------------------------------------------
| Email verification link
|--------------------------------------------------------------------------
|
| Registered outside the api.auth.* name group on purpose: the framework's
| VerifyEmail notification resolves the link by the exact route name
| "verification.verify". The endpoint is public because the signature plus the
| email hash already prove mailbox ownership.
|
*/

// The "api" prefix comes from the api routing group, so the "auth" segment is
// part of the path here: a route level prefix() would be applied after it.
Route::get('auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:email_verification'])
    ->name('verification.verify');