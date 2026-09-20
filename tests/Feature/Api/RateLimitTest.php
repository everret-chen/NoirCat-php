<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ErrorCode;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    public function test_the_rate_limiting_matrix_is_configured(): void
    {
        foreach (['api', 'login', 'register', 'posts', 'uploads', 'search'] as $limiter) {
            $this->assertNotNull(RateLimiter::limiter($limiter), "Missing rate limiter [{$limiter}].");
        }

        $this->assertSame(5, (int) config('noircat.rate_limits.login.per_minute'));
        $this->assertSame(120, (int) config('noircat.rate_limits.api.per_minute'));
    }

    public function test_exceeding_a_limiter_returns_the_rate_limited_envelope(): void
    {
        Route::middleware(['api', 'throttle:login'])
            ->post('/api/_test/login', fn () => response()->json(['code' => 0, 'message' => 'ok', 'data' => null]));

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/_test/login')->assertOk();
        }

        $response = $this->postJson('/api/_test/login');

        $response->assertStatus(429)
            ->assertJsonPath('code', ErrorCode::RATE_LIMITED->value)
            ->assertJsonPath('data', null);
    }
}
