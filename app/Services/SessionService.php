<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

/**
 * Login session (Sanctum personal access token) management.
 *
 * Every query is scoped through the caller's own relation, so a foreign token
 * id can never resolve - there is no ownership check to forget.
 */
class SessionService
{
    public function __construct(private readonly AuditLogService $auditLogs)
    {
    }

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function list(User $user): Collection
    {
        return $user->tokens()
            ->orderByRaw('COALESCE(last_used_at, created_at) DESC')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForApi(User $user): array
    {
        $currentId = $this->currentTokenId($user);

        $sessions = [];

        foreach ($this->list($user) as $token) {
            $sessions[] = [
                'id' => $token->getKey(),
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'is_current' => $token->getKey() === $currentId,
            ];
        }

        return $sessions;
    }

    public function revoke(User $user, int|string $tokenId): void
    {
        $token = $user->tokens()->whereKey($tokenId)->first();

        if ($token === null) {
            throw new BusinessException(ErrorCode::RESOURCE_NOT_FOUND);
        }

        $token->delete();

        $this->auditLogs->record(
            'auth.session.revoked',
            ['token_id' => (int) $tokenId],
            AuditLog::RESULT_SUCCESS,
            $user,
            $user->id,
        );
    }

    /**
     * Revoke every session except the one performing the request.
     */
    public function revokeOthers(User $user): int
    {
        $currentId = $this->currentTokenId($user);
        $revoked = $user->tokens()->whereKeyNot($currentId ?? 0)->delete();

        $this->auditLogs->record(
            'auth.session.revoked_others',
            ['count' => $revoked],
            AuditLog::RESULT_SUCCESS,
            $user,
            $user->id,
        );

        return $revoked;
    }

    private function currentTokenId(User $user): int|string|null
    {
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token->getKey() : null;
    }
}
