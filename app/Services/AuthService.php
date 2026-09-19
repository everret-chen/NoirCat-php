<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
    private function reject(string $account): never
    {
        $this->auditLogs->recordFailure('auth.login', ['account' => $account]);

        throw new BusinessException(ErrorCode::INVALID_CREDENTIALS);
    }
}
