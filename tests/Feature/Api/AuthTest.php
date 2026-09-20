<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ErrorCode;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'username' => 'noircat',
            'email' => 'noircat@example.com',
            'password' => 'S3cret-pass',
            'password_confirmation' => 'S3cret-pass',
        ], $overrides);
    }

    // ------------------------------------------------- 越权类负向用例（硬约束）

    public function test_registration_cannot_assign_a_role(): void
    {
        // Privilege escalation attempt: the client asks for the admin role.
        $this->postJson('/api/auth/register', $this->payload(['role' => 'admin']))->assertCreated();

        $user = User::query()->where('username', 'noircat')->firstOrFail();

        $this->assertSame(UserRole::USER->value, $user->role->value);
        $this->assertTrue($user->hasRole(UserRole::USER->value));
        $this->assertFalse($user->hasRole(UserRole::ADMIN->value));
        $this->assertFalse($user->can(Permission::USER_MANAGE->value));
    }

    public function test_profile_update_cannot_target_another_user(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com']);
        $attacker = User::factory()->create(['email' => 'attacker@example.com']);

        $this->withToken($this->tokenFor($attacker))->putJson('/api/auth/profile', [
            'user_id' => $victim->id,
            'email' => 'pwned@example.com',
        ])->assertOk();

        // The caller may change their own profile; the victim must stay untouched.
        $this->assertSame('victim@example.com', $victim->refresh()->email);
        $this->assertDatabaseMissing('users', ['id' => $victim->id, 'email' => 'pwned@example.com']);
    }
    private function tokenFor(User $user, string $device = 'phpunit'): string
    {
        return $user->createToken($device)->plainTextToken;
    }

    /**
     * Several requests happen inside one test, and the resolved auth guard
     * keeps the user it authenticated on the first one. Clearing the guards
     * makes the next request re-authenticate from scratch, which is what a
     * real client would do.
     */
    private function forgetResolvedGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    // ---------------------------------------------------------------- register

    public function test_registration_creates_a_user_with_the_default_role(): void
    {
        $response = $this->postJson('/api/auth/register', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.user.role', UserRole::USER->value)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => ['token', 'user' => ['id', 'username', 'email', 'role', 'roles', 'permissions']],
            ]);

        $user = User::query()->where('username', 'noircat')->firstOrFail();

        $this->assertTrue($user->hasRole(UserRole::USER->value));
        $this->assertNotSame('S3cret-pass', $user->password);
        $this->assertTrue(Hash::check('S3cret-pass', $user->password));
        $this->assertNotNull($response->json('data.token'));
    }

    public function test_registration_writes_an_audit_entry_without_the_password(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.register',
            'result' => AuditLog::RESULT_SUCCESS,
        ]);

        $payload = (string) json_encode(AuditLog::query()->firstOrFail()->payload);

        $this->assertStringNotContainsString('S3cret-pass', $payload);
    }

    public function test_registration_rejects_a_weak_password(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('code', ErrorCode::VALIDATION_FAILED->value)
            ->assertJsonStructure(['data' => ['errors' => ['password']]]);
    }

    public function test_registration_rejects_a_malformed_username(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['username' => 'bad name!']))
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => ['username']]]);
    }

    public function test_registration_rejects_a_duplicate_username(): void
    {
        User::factory()->create(['username' => 'noircat']);

        $this->postJson('/api/auth/register', $this->payload(['email' => 'other@example.com']))
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => ['username']]]);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'noircat@example.com']);

        $this->postJson('/api/auth/register', $this->payload(['username' => 'other']))
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => ['email']]]);
    }

    // ------------------------------------------------------------------- login

    public function test_login_accepts_username_or_email(): void
    {
        $user = User::factory()->create([
            'username' => 'noir',
            'email' => 'noir@example.com',
        ]);

        foreach (['noir', 'noir@example.com'] as $account) {
            $this->forgetResolvedGuards();

            $this->postJson('/api/auth/login', ['account' => $account, 'password' => 'password'])
                ->assertOk()
                ->assertJsonPath('code', 0)
                ->assertJsonPath('data.user.id', $user->id)
                ->assertJsonStructure(['data' => ['token']]);
        }
    }

    public function test_login_with_a_wrong_password_fails_generically(): void
    {
        User::factory()->create(['username' => 'noir']);

        $this->postJson('/api/auth/login', ['account' => 'noir', 'password' => 'wrong-password'])
            ->assertStatus(401)
            ->assertJsonPath('code', ErrorCode::INVALID_CREDENTIALS->value)
            ->assertJsonPath('data', null);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login',
            'result' => AuditLog::RESULT_FAILURE,
        ]);
    }

    public function test_login_does_not_reveal_whether_the_account_exists(): void
    {
        User::factory()->create(['username' => 'noir']);

        $existing = $this->postJson('/api/auth/login', ['account' => 'noir', 'password' => 'wrong-password']);
        $missing = $this->postJson('/api/auth/login', ['account' => 'ghost', 'password' => 'wrong-password']);

        $this->assertSame($existing->json('code'), $missing->json('code'));
        $this->assertSame($existing->json('message'), $missing->json('message'));
    }

    public function test_login_is_rate_limited_per_ip(): void
    {
        User::factory()->create(['username' => 'noir']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/auth/login', ['account' => 'noir', 'password' => 'wrong-password'])
                ->assertStatus(401);
        }

        $this->postJson('/api/auth/login', ['account' => 'noir', 'password' => 'wrong-password'])
            ->assertStatus(429)
            ->assertJsonPath('code', ErrorCode::RATE_LIMITED->value);
    }

    // ------------------------------------------------------- session lifecycle

    public function test_me_requires_a_valid_token(): void
    {
        $this->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', ErrorCode::UNAUTHENTICATED->value);

        $this->forgetResolvedGuards();

        $this->withToken('not-a-real-token')->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_me_returns_the_authenticated_user_with_roles_and_permissions(): void
    {
        $user = User::factory()->create(['username' => 'noir']);
        $user->assignRole(UserRole::USER->value);

        $response = $this->withToken($this->tokenFor($user))->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.username', 'noir')
            ->assertJsonPath('data.roles', [UserRole::USER->value]);

        $this->assertContains('post:create', $response->json('data.permissions'));
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout']);

        $this->forgetResolvedGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401);
    }

    // ----------------------------------------------------------------- profile

    public function test_profile_email_can_be_updated(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $token = $this->tokenFor($user);

        $this->withToken($token)->putJson('/api/auth/profile', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@example.com']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.profile.update']);
    }

    public function test_profile_rejects_an_email_already_taken(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))->putJson('/api/auth/profile', ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => ['email']]]);
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $payload = ['password' => 'N3w-secret', 'password_confirmation' => 'N3w-secret'];

        $this->withToken($token)->putJson('/api/auth/profile', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => ['current_password']]]);

        $this->forgetResolvedGuards();

        $this->withToken($token)->putJson('/api/auth/profile', $payload + ['current_password' => 'password'])
            ->assertOk();

        $this->assertTrue(Hash::check('N3w-secret', $user->refresh()->password));
    }

    public function test_changing_the_password_revokes_the_other_tokens(): void
    {
        $user = User::factory()->create();
        $current = $this->tokenFor($user, 'current-device');
        $other = $this->tokenFor($user, 'other-device');

        $this->withToken($current)->putJson('/api/auth/profile', [
            'password' => 'N3w-secret',
            'password_confirmation' => 'N3w-secret',
            'current_password' => 'password',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->forgetResolvedGuards();

        $this->withToken($other)->getJson('/api/auth/me')->assertStatus(401);
        $this->withToken($current)->getJson('/api/auth/me')->assertOk();
    }

    // ------------------------------------------------------------------ avatar

    public function test_avatar_upload_accepts_images_and_rejects_other_files(): void
    {
        Storage::fake(AuthService::AVATAR_DISK);

        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->withToken($token)->post('/api/auth/avatar', [
            'avatar' => UploadedFile::fake()->image('me.png', 200, 200),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('code', 0);
        $this->assertNotNull($response->json('data.avatar_url'));

        Storage::disk(AuthService::AVATAR_DISK)->assertExists((string) $user->refresh()->avatar);

        $this->withToken($token)->post('/api/auth/avatar', [
            'avatar' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }
}
