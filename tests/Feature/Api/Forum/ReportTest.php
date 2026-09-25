<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Forum;

use App\Enums\ErrorCode;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The report queue: filing one, then closing it as a moderator.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::USER->value);

        return $user;
    }

    private function moderator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::MODERATOR->value);

        return $user;
    }

    private function tokenFor(User $user, string $device = 'phpunit'): string
    {
        return $user->createToken($device)->plainTextToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Post|Comment $target, string $reason = 'spam', ?string $detail = '测试举报'): array
    {
        return [
            'reportable_type' => $target instanceof Post ? 'post' : 'comment',
            'reportable_id' => $target->id,
            'reason' => $reason,
            'detail' => $detail,
        ];
    }

    #[Test]
    public function a_member_can_report_a_post(): void
    {
        $post = Post::factory()->create();
        $reporter = $this->member();

        $this->withToken($this->tokenFor($reporter))
            ->postJson('/api/reports', $this->payload($post))
            ->assertCreated()
            ->assertJsonPath('data.reason', ReportReason::SPAM->value)
            ->assertJsonPath('data.status', ReportStatus::PENDING->value)
            ->assertJsonPath('data.content_available', true);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reportable_type' => $post->getMorphClass(),
            'reportable_id' => $post->id,
            'status' => ReportStatus::PENDING->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'moderation.report.created']);
    }

    #[Test]
    public function a_comment_can_be_reported_too(): void
    {
        $comment = Comment::factory()->create(['content' => '垃圾评论']);

        $this->withToken($this->tokenFor($this->member()))
            ->postJson('/api/reports', $this->payload($comment, 'abuse'))
            ->assertCreated()
            ->assertJsonPath('data.reportable_type', 'Comment')
            ->assertJsonPath('data.reportable.excerpt', '垃圾评论');
    }

    #[Test]
    public function reporting_the_same_content_twice_is_rejected(): void
    {
        $post = Post::factory()->create();
        $token = $this->tokenFor($this->member());

        $this->withToken($token)->postJson('/api/reports', $this->payload($post))->assertCreated();

        $this->withToken($token)
            ->postJson('/api/reports', $this->payload($post, 'abuse', null))
            ->assertStatus(409)
            ->assertJsonPath('code', ErrorCode::BUSINESS_RULE_VIOLATION->value);

        $this->assertDatabaseCount('reports', 1);
    }

    #[Test]
    public function you_cannot_report_your_own_content(): void
    {
        $author = $this->member();
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->withToken($this->tokenFor($author))
            ->postJson('/api/reports', $this->payload($post))
            ->assertStatus(422)
            ->assertJsonPath('message', __('forum.errors.report_own'));
    }

    #[Test]
    public function a_reason_outside_the_list_is_rejected(): void
    {
        $post = Post::factory()->create();

        $this->withToken($this->tokenFor($this->member()))
            ->postJson('/api/reports', $this->payload($post, 'because-i-say-so'))
            ->assertStatus(422)
            ->assertJsonPath('code', ErrorCode::VALIDATION_FAILED->value);
    }

    #[Test]
    public function an_unsupported_target_is_rejected(): void
    {
        $user = $this->member();

        $this->withToken($this->tokenFor($this->member()))
            ->postJson('/api/reports', [
                'reportable_type' => 'user',
                'reportable_id' => $user->id,
                'reason' => 'spam',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function reporting_requires_an_account(): void
    {
        $post = Post::factory()->create();

        $this->postJson('/api/reports', $this->payload($post))->assertStatus(401);
    }

    #[Test]
    public function only_handlers_can_read_the_queue(): void
    {
        Report::factory()->create();

        $this->withToken($this->tokenFor($this->member()))
            ->getJson('/api/reports')
            ->assertStatus(403)
            ->assertJsonPath('code', ErrorCode::FORBIDDEN->value);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($this->moderator()))
            ->getJson('/api/reports')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    public function the_queue_can_be_filtered_by_status_and_reason(): void
    {
        Report::factory()->create(['reason' => ReportReason::SPAM]);
        Report::factory()->create(['reason' => ReportReason::ABUSE]);

        $token = $this->tokenFor($this->moderator());

        $this->withToken($token)->getJson('/api/reports?reason=spam')->assertOk()->assertJsonPath('meta.total', 1);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/reports?status=all')->assertOk()->assertJsonPath('meta.total', 2);
    }

    #[Test]
    public function a_moderator_can_resolve_or_dismiss_a_report(): void
    {
        $report = Report::factory()->create();
        $moderator = $this->moderator();
        $token = $this->tokenFor($moderator);

        $this->withToken($token)
            ->postJson('/api/reports/'.$report->id.'/resolve', ['note' => '已隐藏该评论'])
            ->assertOk()
            ->assertJsonPath('data.status', ReportStatus::RESOLVED->value)
            ->assertJsonPath('data.resolution_note', '已隐藏该评论');

        $report->refresh();
        $this->assertSame($moderator->id, $report->handled_by);
        $this->assertNotNull($report->handled_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'moderation.report.resolved']);

        // A closed report cannot be closed again.
        $this->withToken($token)
            ->postJson('/api/reports/'.$report->id.'/dismiss')
            ->assertStatus(409)
            ->assertJsonPath('message', __('forum.errors.report_already_handled'));
    }

    #[Test]
    public function a_member_cannot_close_a_report(): void
    {
        $report = Report::factory()->create();

        $this->withToken($this->tokenFor($this->member()))
            ->postJson('/api/reports/'.$report->id.'/dismiss')
            ->assertStatus(403);

        $this->assertTrue($report->refresh()->isOpen());
    }

    #[Test]
    public function a_report_about_deleted_content_stays_in_the_queue_as_orphaned(): void
    {
        $post = Post::factory()->create();
        $report = Report::factory()->create([
            'reportable_type' => $post->getMorphClass(),
            'reportable_id' => $post->id,
        ]);
        $post->delete();

        $this->withToken($this->tokenFor($this->moderator()))
            ->getJson('/api/reports')
            ->assertOk()
            ->assertJsonPath('data.0.content_available', false)
            ->assertJsonPath('data.0.reportable', null);

        // The moderator can still close it.
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($this->moderator()))
            ->postJson('/api/reports/'.$report->id.'/resolve')
            ->assertOk()
            ->assertJsonPath('data.status', ReportStatus::RESOLVED->value);
    }
}
