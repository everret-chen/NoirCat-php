<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Each key maps to a directory under lang/ and is used by the SetLocale
    | middleware to resolve the request locale.
    |
    */

    'locales' => [
        'zh_CN' => '简体中文',
        'en' => 'English',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting Matrix
    |--------------------------------------------------------------------------
    |
    | Consumed by the named limiters registered in
    | AppServiceProvider::configureRateLimiting(). Tune here, not in routes.
    |
    */

    'rate_limits' => [
        'api' => ['per_minute' => 120],
        'login' => ['per_minute' => 5, 'per_day' => 50],
        'register' => ['per_hour' => 3],
        'posts' => ['per_minute' => 10],
        'uploads' => ['per_hour' => 20],
        'search' => ['per_minute' => 60],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Keys that must never be persisted inside audit_logs.payload.
    |
    */

    'audit' => [
        'redacted_keys' => [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'access_token',
            'refresh_token',
            'remember_token',
            'secret',
            'api_key',
            'authorization',
        ],
    ],

];
