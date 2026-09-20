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
        $user = DB::transaction(function () use ($attributes): User {
            $user = User::create([
                'username' => (string) $attributes['username'],
                'email' => (string) $attributes['email'],
                'password' => (string) $attributes['password'],
                'role' => UserRole::USER,
            ]);

            $user->assignRole(UserRole::USER->value);

            return $user;
        });

        $this->auditLogs->record(
            'auth.register',
            ['username' => $user->username],
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
            Hash::check($password, self::TIMING_EQUALIZER_HASH);

            $this->reject($account);
        }

        if (! Hash::check($password, $user->password)) {
            $this->reject($account);
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
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } else {
            // Session-authenticated request: no single token to revoke.
            $user->tokens()->delete();
        }

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
        // store() derives the filename and extension from the detected MIME
        // type, never from the client supplied name.
        $path = $file->store('avatars/'.$user->id, self::AVATAR_DISK);

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
        $status = Password::broker()->reset($credentials, function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();
        });

        $email = (string) ($credentials['email'] ?? '');
        $user = User::query()->where('email', $email)->first();

        if ($status !== Password::PASSWORD_RESET || $user === null) {
            $this->auditLogs->recordFailure('auth.password.reset', ['email' => $email]);

            throw new BusinessException(ErrorCode::BUSINESS_RULE_VIOLATION, __($status), 422);
        }

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

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            $this->auditLogs->recordFailure('auth.email.verify_failed', [], $user, $user->id);

            throw new BusinessException(ErrorCode::FORBIDDEN, __('api.errors.VERIFICATION_LINK_INVALID'), 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));

            $this->auditLogs->record('auth.email.verified', [], AuditLog::RESULT_SUCCESS, $user, $user->id);
        }

        return $user;
    }

    private function reject(string $account): never
    {
        $this->auditLogs->recordFailure('auth.login', ['account' => $account]);

        throw new BusinessException(ErrorCode::INVALID_CREDENTIALS);
    }
}
