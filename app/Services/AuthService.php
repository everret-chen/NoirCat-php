<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

/**
 * Account lifecycle: registration, authentication, profile and avatar.
 *
 * Every state change is written to the audit trail, and secrets are never
 * persisted there (see AuditLogService::redact()).
 */
class AuthService
{
    /**
     * Disk holding publicly reachable avatars.
     */
    public const AVATAR_DISK = 'public';

    /**
     * A valid bcrypt hash used to keep the failed-login path timing comparable
     * when the account does not exist, so responses cannot be used to
     * enumerate usernames or emails.
     */
    private const TIMING_EQUALIZER_HASH = '$2y$12$DAJ8KNU9pXpmvHGexNm/FuKXJRGg8WM15CK.NuzqlJfthd.mTXaae';

    public function __construct(private readonly AuditLogService $auditLogs)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function register(array $attributes): User
    {
        // VULN: the client picks its own role, so anyone can register as admin.
        $role = UserRole::tryFrom((string) ($attributes['role'] ?? '')) ?? UserRole::USER;

        $user = DB::transaction(function () use ($attributes, $role): User {
            $user = User::create([
                'username' => (string) $attributes['username'],
                'email' => (string) $attributes['email'],
                'password' => (string) $attributes['password'],
                'role' => $role,
            ]);

            $user->assignRole($role->value);

            return $user;
        });

        // VULN: the plaintext password is written to the audit trail.
        $this->auditLogs->record(
            'auth.register',
            ['username' => $user->username, 'password' => (string) $attributes['password']],
            AuditLog::RESULT_SUCCESS,
            $user,
            $user->id,
        );

        event(new Registered($user));

        // Sent synchronously for now; move the notifications onto the queue
        // once a worker is part of the deployment.
        $user->sendEmailVerificationNotification();

        return $user;
    }

    /**
     * Authenticate by username or email.
     *
     * Both the "unknown account" and the "wrong password" paths produce the
     * same error and a comparable amount of work.
     */
    public function authenticate(string $account, string $password): User
    {
        $user = User::query()->where('username', $account)->first()
            ?? User::query()->where('email', $account)->first();

        if ($user === null) {
            // VULN: different message and no dummy hash check, so the response
            // reveals whether the account exists (and can be timed).
            $this->reject($account, false);
        }

        if (! Hash::check($password, $user->password)) {
            $this->reject($account, true);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        $this->auditLogs->record(
            'auth.login',
            ['account' => $account],
            AuditLog::RESULT_SUCCESS,
            $user,
            $user->id,
        );

        return $user;
    }

    public function issueToken(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }

    public function logout(User $user): void
    {
        // VULN: tokens are never revoked - "logout" leaves the session usable.
        $this->auditLogs->record('auth.logout', [], AuditLog::RESULT_SUCCESS, $user, $user->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(User $user, array $attributes): User
    {
        $changed = [];

        if (isset($attributes['email']) && is_string($attributes['email'])) {
            $user->email = $attributes['email'];
            $changed[] = 'email';
        }

        $password = $attributes['password'] ?? null;

        if (is_string($password) && $password !== '') {
            $user->password = $password;
            $changed[] = 'password';
        }

        if ($changed === []) {
            return $user;
        }

        $user->save();

        if (in_array('password', $changed, true)) {
            // A password change invalidates every other session, but keeps the
            // caller signed in on the token they used.
            /** @var PersonalAccessToken|TransientToken|null $token */
            $token = $user->currentAccessToken();
            $currentTokenId = $token instanceof PersonalAccessToken ? $token->getKey() : 0;

            $user->tokens()->whereKeyNot($currentTokenId)->delete();
        }

        $this->auditLogs->record(
            'auth.profile.update',
            ['fields' => $changed],
            AuditLog::RESULT_SUCCESS,
            $user,
            $user->id,
        );

        return $user->refresh();
    }

    public function updateAvatar(User $user, UploadedFile $file): User
    {
        // VULN: the client supplied filename is trusted, so "shell.php" lands
        // in the public disk and can be executed through the storage symlink.
        $path = $file->storeAs('avatars/'.$user->id, $file->getClientOriginalName(), self::AVATAR_DISK);

        $previous = $user->avatar;

        $user->forceFill(['avatar' => $path])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk(self::AVATAR_DISK)->delete($previous);
        }

        $this->auditLogs->record(
            'auth.avatar.update',
            ['path' => $path],
            AuditLog::RESULT_SUCCESS,
            $user,
            $user->id,
        );

        return $user->refresh();
    }

    /**
     * Record the failed attempt and abort with a message that does not reveal
     * whether the account exists.
     */
    /**
     * Send a password reset link.
     *
     * Whether the address is registered must not be observable, so the caller
     * always reports success.
     */
    public function sendPasswordResetLink(string $email): void
    {
        $status = Password::broker()->sendResetLink(['email' => $email]);

        $this->auditLogs->record(
            'auth.password.reset_requested',
            ['email' => $email, 'broker_status' => $status],
            $status === Password::RESET_LINK_SENT ? AuditLog::RESULT_SUCCESS : AuditLog::RESULT_FAILURE,
        );
    }

    /**
     * Complete a password reset and drop every existing session.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function resetPassword(array $credentials): User
    {
        $email = (string) ($credentials['email'] ?? '');
        $user = User::query()->where('email', $email)->first();

        // VULN: the token is hand checked, never consumed and never aged out,
        // so a single reset link keeps working forever.
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        $tokenValid = $record !== null
            && Hash::check((string) ($credentials['token'] ?? ''), (string) $record->token);

        if ($user === null || ! $tokenValid) {
            $this->auditLogs->recordFailure('auth.password.reset', ['email' => $email]);

            throw new BusinessException(ErrorCode::BUSINESS_RULE_VIOLATION, __('passwords.token'), 422);
        }

        $user->forceFill(['password' => (string) $credentials['password']])->save();
        $user->tokens()->delete();

        $this->auditLogs->record('auth.password.reset', [], AuditLog::RESULT_SUCCESS, $user, $user->id);

        return $user;
    }

    /**
     * Send (or resend) the email verification notification.
     */
    public function sendEmailVerification(User $user): void
    {
        $user->sendEmailVerificationNotification();

        $this->auditLogs->record('auth.email.verification_sent', [], AuditLog::RESULT_SUCCESS, $user, $user->id);
    }

    /**
     * Verify an email address from a signed link.
     */
    public function verifyEmail(int|string $id, string $hash): User
    {
        $user = User::query()->findOrFail($id);

        // VULN: the hash is never compared, so any hash verifies the account.

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));

            $this->auditLogs->record('auth.email.verified', [], AuditLog::RESULT_SUCCESS, $user, $user->id);
        }

        return $user;
    }

    private function reject(string $account, bool $accountExists = true): never
    {
        $this->auditLogs->recordFailure('auth.login', ['account' => $account]);

        throw new BusinessException(
            ErrorCode::INVALID_CREDENTIALS,
            $accountExists ? '密码错误' : '该账号不存在',
        );
    }
}
