<?php

declare(strict_types=1);

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
| Every response uses the { code, message, data } envelope.
|
*/

Route::get('/health', HealthController::class)->name('api.health');
