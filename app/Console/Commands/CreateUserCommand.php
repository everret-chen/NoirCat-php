<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creates an account from the command line.
 *
 * Local setups often have no working mail channel, and a fresh installation has
 * no account at all (the seeders deliberately ship no default credentials), so
 * this is how the first administrator gets in. Console accounts are marked
 * verified because the operator vouches for the address, and the action is
 * written to the audit trail like any other account change.
 */
class CreateUserCommand extends Command
{
    protected $signature = 'noircat:user:create
        {email : The email address of the new account}
        {--username= : Login name (defaults to the part before the @)}
        {--password= : Password (a strong one is generated when omitted)}
        {--role=user : user, moderator, guild_admin or admin}
        {--unverified : Leave the address unverified instead of trusting the operator}';

    protected $description = 'Create an account (optionally an administrator) without going through registration';

    public function handle(AuditLogService $auditLogs): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $username = trim((string) ($this->option('username') ?: $this->usernameFrom($email)));
        $role = (string) $this->option('role');
        $password = (string) ($this->option('password') ?: Str::password(16));

        $validator = Validator::make(
            ['email' => $email, 'username' => $username, 'password' => $password, 'role' => $role],
            [
                'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
                'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('users', 'username')],
                'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
                'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = UserRole::from($role);

        if (! $this->option('unverified')) {
            $user->email_verified_at = now();
        }

        $user->save();
        $user->assignRole($role);

        $auditLogs->record(
            'auth.user.created',
            ['channel' => 'console', 'role' => $role, 'email_verified' => ! $this->option('unverified')],
            AuditLog::RESULT_SUCCESS,
            $user,
            null,
        );

        $this->components->info("Account {$user->username} <{$user->email}> created with role {$role}.");

        if (! $this->option('unverified')) {
            $this->components->twoColumnDetail('Email status', 'verified (created from the console)');
        } else {
            $this->components->twoColumnDetail('Email status', 'unverified - a confirmation mail is required');
        }

        if (! $this->option('password')) {
            // Printed once and never stored: hand it over, then change it.
            $this->components->twoColumnDetail('Password', $password);
        }

        return self::SUCCESS;
    }

    /**
     * The local part of an address can hold characters a username may not
     * (dots, plus signs), so it is sanitised rather than used verbatim; the
     * validation above still rejects anything too short to be a username.
     */
    private function usernameFrom(string $email): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '_', Str::before($email, '@'));
    }
}
