<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * GET /api/health
     *
     * Liveness probe for the API; also the reference implementation of the
     * response envelope and locale resolution.
     */
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'locale' => app()->getLocale(),
            'time' => now()->toIso8601String(),
        ], __('common.health_ok'));
    }
}
