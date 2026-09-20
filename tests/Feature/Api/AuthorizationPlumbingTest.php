<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ErrorCode;
use App\Enums\UserRole;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Verifies the authorization plumbing the module policies will rely on:
 * spatie's permission middleware and the project's "verified" middleware.
 *
 * The guard argument (",sanctum") only selects which auth guard supplies the
 * user; the permission lookup itself uses the user's own guard, which is why
 * the seeded permissions stay on the default "web" guard.
 */
class AuthorizationPlumbingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Route::middleware(['api', 'auth:sanctum', 'permission:user:manage,sanctum'])
            ->get('/api/_test/admin-only', fn () => ApiResponse::success(['ok' => true]));

        Route::middleware(['api', 'auth:sanctum', 'verified'])
            ->get('/api/_test/verified-only', fn () => ApiResponse::success(['ok' => true]));
    }

    private function tokenFor(User $user, string $device = 'phpunit'): string
    {
        return $user->createToken($device)->plainTextToken;
    }

    public function test_a_member_without_the_permission_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole(UserRole::USER->value);

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/_test/admin-only')
            ->assertStatus(403)
            ->assertJsonPath('code', ErrorCode::FORBIDDEN->value)
            ->assertJsonPath('data', null);
    }

    public function test_an_admin_reaches_the_permission_protected_route(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/_test/admin-only')
            ->assertOk()
            ->assertJsonPath('data.ok', true);
    }

    public function test_a_guest_is_rejected_before_the_permission_check(): void
    {
        $this->getJson('/api/_test/admin-only')
            ->assertStatus(401)
            ->assertJsonPath('code', ErrorCode::UNAUTHENTICATED->value);
    }

    public function test_an_unverified_account_is_blocked_with_code_1005(): void
    {
        $user = User::factory()->unverified()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/_test/verified-only')
            ->assertStatus(403)
            ->assertJsonPath('code', ErrorCode::EMAIL_NOT_VERIFIED->value)
            ->assertJsonPath('message', __('api.errors.EMAIL_NOT_VERIFIED'));
    }

    public function test_a_verified_account_passes_the_verified_middleware(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/_test/verified-only')
            ->assertOk()
            ->assertJsonPath('data.ok', true);
    }
}
