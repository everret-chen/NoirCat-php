<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorEnvelopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api')->group(function (): void {
            Route::post('/_test/validate', function (Request $request) {
                $request->validate(['username' => ['required', 'string', 'max:3']]);

                return response()->json(['validated' => true]);
            });

            Route::get('/_test/business', function () {
                throw BusinessException::make(ErrorCode::BUSINESS_RULE_VIOLATION, 'banned word');
            });

            Route::get('/_test/boom', function () {
                throw new RuntimeException('kaboom-secret');
            });
        });
    }

    public function test_unknown_api_route_returns_route_not_found(): void
    {
        $response = $this->getJson('/api/does-not-exist');

        $response->assertNotFound()
            ->assertJsonPath('code', ErrorCode::ROUTE_NOT_FOUND->value)
            ->assertJsonPath('data', null);
    }

    public function test_wrong_http_method_returns_method_not_allowed(): void
    {
        $response = $this->postJson('/api/health');

        $response->assertStatus(405)
            ->assertJsonPath('code', ErrorCode::METHOD_NOT_ALLOWED->value)
            ->assertJsonPath('data', null);
    }

    public function test_validation_failure_returns_field_errors(): void
    {
        $response = $this->postJson('/api/_test/validate', ['username' => 'too-long-name']);

        $response->assertStatus(422)
            ->assertJsonPath('code', ErrorCode::VALIDATION_FAILED->value)
            ->assertJsonStructure(['code', 'message', 'data' => ['errors' => ['username']]]);
    }

    public function test_business_exception_keeps_its_code_and_message(): void
    {
        $response = $this->getJson('/api/_test/business');

        $response->assertStatus(400)
            ->assertJsonPath('code', ErrorCode::BUSINESS_RULE_VIOLATION->value)
            ->assertJsonPath('message', 'banned word')
            ->assertJsonPath('data', null);
    }

    public function test_internal_errors_are_reported_with_the_system_code(): void
    {
        $response = $this->getJson('/api/_test/boom');

        $response->assertStatus(500)
            ->assertJsonPath('code', ErrorCode::SYSTEM_ERROR->value);
    }

    public function test_internal_error_details_are_hidden_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/_test/boom');

        $response->assertStatus(500)
            ->assertJsonPath('code', ErrorCode::SYSTEM_ERROR->value)
            ->assertJsonPath('message', ErrorCode::SYSTEM_ERROR->message())
            ->assertJsonPath('data', null);

        $this->assertStringNotContainsString('kaboom-secret', $response->getContent());
    }

    public function test_web_requests_keep_the_default_error_page(): void
    {
        $response = $this->get('/not-a-web-route');

        $response->assertNotFound();
        $this->assertStringNotContainsString('"code"', $response->getContent());
    }
}
