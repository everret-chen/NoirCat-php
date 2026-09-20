<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The console is how a fresh installation gets its first administrator, so the
 * command has to produce an account that can actually post.
 */
class NoircatCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function it_creates_a_verified_administrator(): void
    {
        $this->artisan('noircat:user:create', [
            'email' => 'root@noircat.test',
            '--username' => 'root',
            '--password' => 'S3cret-pass',
            '--role' => UserRole::ADMIN->value,
        ])->assertSuccessful();

        $user = User::query()->where('username', 'root')->firstOrFail();

        $this->assertSame('root@noircat.test', $user->email);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->hasRole(UserRole::ADMIN->value));
        $this->assertTrue(Hash::check('S3cret-pass', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.user.created']);
    }

    #[Test]
    public function it_lowercases_the_address_and_sanitises_the_derived_username(): void
    {
        $this->artisan('noircat:user:create', [
            'email' => 'Mixed.Case@NoirCat.test',
            '--password' => 'S3cret-pass',
        ])->assertSuccessful();

        $user = User::query()->firstOrFail();

        $this->assertSame('mixed.case@noircat.test', $user->email);
        // A dot is not allowed in a username, so it becomes an underscore.
        $this->assertSame('mixed_case', $user->username);
        $this->assertTrue($user->hasRole(UserRole::USER->value));
    }

    #[Test]
    public function it_can_leave_the_address_unverified(): void
    {
        $this->artisan('noircat:user:create', [
            'email' => 'pending@noircat.test',
            '--password' => 'S3cret-pass',
            '--unverified' => true,
        ])->assertSuccessful();

        $this->assertFalse(User::query()->firstOrFail()->hasVerifiedEmail());
    }

    #[Test]
    public function it_rejects_a_duplicate_address_and_a_weak_password(): void
    {
        User::factory()->create(['email' => 'taken@noircat.test']);

        $this->artisan('noircat:user:create', [
            'email' => 'taken@noircat.test',
            '--password' => 'S3cret-pass',
        ])->assertFailed();

        $this->artisan('noircat:user:create', [
            'email' => 'weak@noircat.test',
            '--password' => 'password',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'weak@noircat.test']);
    }

    #[Test]
    public function the_mail_command_prints_the_newest_link(): void
    {
        $path = storage_path('framework/testing/mail.log');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode("\n", [
            'http://localhost:8000/email/verify/1/aaa?expires=1790000001&signature=oldest',
            'http://localhost:8000/email/verify/2/bbb?expires=1790000002&signature=newest',
        ]));

        $this->artisan('noircat:mail:latest', ['--limit' => 2, '--path' => $path])
            ->expectsOutputToContain('signature=newest')
            ->assertSuccessful();

        File::delete($path);
    }

    #[Test]
    public function the_mail_command_reports_an_empty_log(): void
    {
        $path = storage_path('framework/testing/empty-mail.log');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'no links here');

        $this->artisan('noircat:mail:latest', ['--path' => $path])
            ->expectsOutputToContain('No verification or reset link found')
            ->assertSuccessful();

        File::delete($path);
    }
}
