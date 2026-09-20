<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ErrorCode;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_is_sent_without_revealing_whether_the_address_exists(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'noir@example.com']);

        $this->postJson('/api/auth/password/email', ['email' => 'noir@example.com'])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', __('api.messages.password_reset_link_sent'));

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        Notification::fake();

        $this->postJson('/api/auth/password/email', ['email' => 'ghost@example.com'])
            ->assertOk()
            ->assertJsonPath('message', __('api.messages.password_reset_link_sent'));

        Notification::assertNothingSent();
    }

    public function test_a_valid_token_resets_the_password_and_drops_every_session(): void
    {
        $user = User::factory()->create(['email' => 'noir@example.com']);
        $user->createToken('old-device');

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/password/reset', [
            'email' => 'noir@example.com',
            'token' => $token,
            'password' => 'N3w-secret',
            'password_confirmation' => 'N3w-secret',
        ])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertTrue(Hash::check('N3w-secret', $user->refresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password.reset']);
    }

    public function test_an_invalid_token_is_rejected_and_the_password_stays(): void
    {
        $user = User::factory()->create(['email' => 'noir@example.com']);

        $this->postJson('/api/auth/password/reset', [
            'email' => 'noir@example.com',
            'token' => 'not-a-real-token',
            'password' => 'N3w-secret',
            'password_confirmation' => 'N3w-secret',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', ErrorCode::BUSINESS_RULE_VIOLATION->value);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password.reset', 'result' => 'failure']);
    }

    public function test_a_reset_token_cannot_be_used_twice(): void
    {
        $user = User::factory()->create(['email' => 'noir@example.com']);
        $token = Password::broker()->createToken($user);

        $payload = [
            'email' => 'noir@example.com',
            'token' => $token,
            'password' => 'N3w-secret',
            'password_confirmation' => 'N3w-secret',
        ];

        $this->postJson('/api/auth/password/reset', $payload)->assertOk();

        // The broker consumes the token, so replaying it must fail.
        $this->postJson('/api/auth/password/reset', [
            'email' => 'noir@example.com',
            'token' => $token,
            'password' => 'Other-secret',
            'password_confirmation' => 'Other-secret',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', ErrorCode::BUSINESS_RULE_VIOLATION->value);

        $this->assertTrue(Hash::check('N3w-secret', $user->refresh()->password));
    }

    public function test_a_weak_new_password_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'noir@example.com']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/password/reset', [
            'email' => 'noir@example.com',
            'token' => $token,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', ErrorCode::VALIDATION_FAILED->value)
            ->assertJsonStructure(['data' => ['errors' => ['password']]]);
    }

    public function test_the_reset_request_endpoint_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'noir@example.com']);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/auth/password/email', ['email' => 'noir@example.com'])->assertOk();
        }

        $this->postJson('/api/auth/password/email', ['email' => 'noir@example.com'])
            ->assertStatus(429)
            ->assertJsonPath('code', ErrorCode::RATE_LIMITED->value);
    }
}
