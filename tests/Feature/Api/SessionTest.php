<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ErrorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_are_listed_for_the_caller_only(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current-device')->plainTextToken;
        $user->createToken('other-device');

        $stranger = User::factory()->create();
        $strangerToken = $stranger->createToken('stranger-device')->accessToken;

        $response = $this->withToken($current)->getJson('/api/auth/sessions')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertCount(2, $ids);
        $this->assertNotContains($strangerToken->getKey(), $ids);
        $this->assertSame(1, collect($response->json('data'))->where('is_current', true)->count());
    }

    public function test_a_listed_session_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current-device')->plainTextToken;
        $other = $user->createToken('other-device')->accessToken;

        $this->withToken($current)
            ->deleteJson('/api/auth/sessions/'.$other->getKey())
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.session.revoked']);

        $this->app['auth']->forgetGuards();

        $this->withToken($current)->getJson('/api/auth/me')->assertOk();
    }

    public function test_another_users_session_cannot_be_revoked(): void
    {
        $victim = User::factory()->create();
        $victimToken = $victim->createToken('victim-device')->accessToken;

        $attacker = User::factory()->create();
        $attackerToken = $attacker->createToken('attacker-device')->plainTextToken;

        $this->withToken($attackerToken)
            ->deleteJson('/api/auth/sessions/'.$victimToken->getKey())
            ->assertStatus(404)
            ->assertJsonPath('code', ErrorCode::RESOURCE_NOT_FOUND->value);

        // Nothing was revoked: both tokens are still there.
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_every_other_session_can_be_revoked_at_once(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current-device')->plainTextToken;
        $other = $user->createToken('other-device')->plainTextToken;
        $user->createToken('third-device');

        $this->withToken($current)
            ->deleteJson('/api/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data.revoked', 2);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        // The resolved guard caches its user, so each request that must
        // re-authenticate starts from a clean manager.
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/auth/me')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_session_management_requires_authentication(): void
    {
        $this->getJson('/api/auth/sessions')
            ->assertStatus(401)
            ->assertJsonPath('code', ErrorCode::UNAUTHENTICATED->value);
    }
}
