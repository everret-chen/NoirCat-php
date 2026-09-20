<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes security relevant operations to audit_logs.
 *
 * Sensitive payload keys are redacted before persisting: the audit trail must
 * never become a second place where credentials leak.
 */
class AuditLogService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $action,
        array $payload = [],
        string $result = AuditLog::RESULT_SUCCESS,
        ?Model $subject = null,
        int|string|null $actorId = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $actorId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'result' => $result,
            'ip' => request()->ip(),
            'user_agent' => $this->userAgent(),
            'payload' => $this->redact($payload),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordFailure(
        string $action,
        array $payload = [],
        ?Model $subject = null,
        int|string|null $actorId = null,
    ): AuditLog {
        return $this->record($action, $payload, AuditLog::RESULT_FAILURE, $subject, $actorId);
    }

    /**
     * Replace the values of sensitive keys while keeping their names.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        $sensitive = array_map('strtolower', (array) config('noircat.audit.redacted_keys', []));

        return $this->redactRecursive($payload, array_values($sensitive));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $sensitive
     * @return array<string, mixed>
     */
    private function redactRecursive(array $payload, array $sensitive): array
    {
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $payload[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $payload[$key] = $this->redactRecursive($value, $sensitive);
            }
        }

        return $payload;
    }

    private function userAgent(): ?string
    {
        $agent = request()->userAgent();

        return $agent === null ? null : mb_substr($agent, 0, 255);
    }
}
