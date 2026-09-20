<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_an_audit_entry_for_the_actor(): void
    {
        $user = User::factory()->create();

        $log = app(AuditLogService::class)->record(
            'user.login',
            ['channel' => 'web'],
            AuditLog::RESULT_SUCCESS,
            null,
            $user->id,
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'user_id' => $user->id,
            'action' => 'user.login',
            'result' => AuditLog::RESULT_SUCCESS,
        ]);

        $this->assertSame('web', $log->payload['channel']);
        $this->assertNotNull($log->ip);
    }

    public function test_redacts_sensitive_payload_keys_recursively(): void
    {
        $log = app(AuditLogService::class)->record('user.register', [
            'username' => 'noir',
            'password' => 'super-secret',
            'nested' => ['token' => 'abc123', 'keep' => 'visible'],
        ]);

        $this->assertSame('[redacted]', $log->payload['password']);
        $this->assertSame('[redacted]', $log->payload['nested']['token']);
        $this->assertSame('visible', $log->payload['nested']['keep']);
        $this->assertStringNotContainsString('super-secret', json_encode($log->payload));
        $this->assertStringNotContainsString('abc123', json_encode($log->payload));
    }

    public function test_stores_the_morph_subject_and_failure_result(): void
    {
        $user = User::factory()->create();

        $log = app(AuditLogService::class)->recordFailure('user.delete', [], $user, $user->id);

        $this->assertSame(AuditLog::RESULT_FAILURE, $log->result);
        $this->assertSame(User::class, $log->auditable_type);
        $this->assertSame((string) $user->id, (string) $log->auditable_id);
    }

    public function test_keeps_the_trail_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();

        app(AuditLogService::class)->record('user.login', [], AuditLog::RESULT_SUCCESS, null, $user->id);

        $user->delete();

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.login', 'user_id' => null]);
    }
}
