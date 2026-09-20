<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ErrorCode;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('phpunit')->plainTextToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'username' => 'noircat',
            'email' => 'noircat@example.com',
            'password' => 'S3cret-pass',
            'password_confirmation' => 'S3cret-pass',
        ];
    }

    public function test_registration_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $user = User::query()->where('username', 'noircat')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_a_signed_link_verifies_the_email_address(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('api.auth.verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.email', $user->email);

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email.verified']);
    }

    public function test_a_link_whose_hash_does_not_match_the_address_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('api.auth.verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.com'),
        ]);

        $this->getJson($url)
            ->assertStatus(403)
            ->assertJsonPath('code', ErrorCode::FORBIDDEN->value);

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email.verify_failed']);
    }

    public function test_unsigned_and_expired_links_are_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $hash = sha1($user->getEmailForVerification());

        $this->getJson("/api/auth/email/verify/{$user->id}/{$hash}")
            ->assertStatus(403);

        $expired = URL::temporarySignedRoute('api.auth.verification.verify', now()->subMinute(), [
            'id' => $user->id,
            'hash' => $hash,
        ]);

        $this->getJson($expired)->assertStatus(403);

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_the_verification_email_can_be_resent(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('code', 0);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email.verification_sent']);
    }

    public function test_an_already_verified_address_is_not_mailed_again(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        Notification::fake();

        $this->withToken($token)->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', __('api.messages.already_verified'));

        Notification::assertNothingSent();
    }

    public function test_the_resend_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/auth/email/verification-notification')
            ->assertStatus(401)
            ->assertJsonPath('code', ErrorCode::UNAUTHENTICATED->value);
    }
}
