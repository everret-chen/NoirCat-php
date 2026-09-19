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

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('profile.update');

        Route::post('avatar', [AuthController::class, 'uploadAvatar'])
            ->middleware('throttle:uploads')
            ->name('avatar');
    });
});
