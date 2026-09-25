<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\CommentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The governance pages: the report queue, the trash, and the moderator actions
 * reachable from a thread. These are the paths a moderator actually clicks, so
 * they are covered separately from the API.
 *
 * UI strings are asserted through __() rather than as literals: the test client
 * sends an Accept-Language header, so the rendered locale is not guaranteed.
 */
class ModerationPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Pins the locale for every request in this class: the test client sends
        // an Accept-Language header, which would otherwise render the pages in
        // English while __() in the assertions resolves against the app default.
        $this->withHeader('X-Locale', 'zh_CN');
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

    #[Test]
    public function a_member_is_denied_the_report_queue_but_keeps_their_own_trash(): void
    {
        $this->actingAs($this->member())->get('/moderation/reports')->assertForbidden();
        $this->actingAs($this->member())->get('/moderation/trash')->assertOk();
    }

    #[Test]
    public function the_report_queue_renders_for_a_moderator(): void
    {
        $post = Post::factory()->create(['title' => '被举报的帖子']);
        Report::factory()->create([
            'reportable_type' => $post->getMorphClass(),
            'reportable_id' => $post->id,
            'reason' => ReportReason::SPAM,
            'detail' => '这是广告',
        ]);

        $this->actingAs($this->moderator())
            ->get('/moderation/reports')
            ->assertOk()
            ->assertSee(__('moderation_ui.reports.title'))
            ->assertSee('被举报的帖子')
            ->assertSee('这是广告')
            ->assertSee(ReportReason::SPAM->label());
    }

    #[Test]
    public function the_queue_can_be_filtered_by_status(): void
    {
        Report::factory()->create(['detail' => '待处理的那条']);
        Report::factory()->handled(ReportStatus::DISMISSED)->create(['detail' => '已经忽略的那条']);

        // The status labels also appear inside the filter select, so the filter
        // is asserted through the rows that are actually listed.
        $this->actingAs($this->moderator())
            ->get('/moderation/reports?status=all')
            ->assertOk()
            ->assertSee('待处理的那条')
            ->assertSee('已经忽略的那条');

        $this->actingAs($this->moderator())
            ->get('/moderation/reports')
            ->assertOk()
            ->assertSee('待处理的那条')
            ->assertDontSee('已经忽略的那条');
    }

    #[Test]
    public function a_moderator_closes_a_report_from_the_page(): void
    {
        $report = Report::factory()->create();
        $moderator = $this->moderator();

        $this->actingAs($moderator)
            ->post(route('moderation.reports.resolve', $report), ['note' => '已隐藏'])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(ReportStatus::RESOLVED, $report->status);
        $this->assertSame($moderator->id, $report->handled_by);
        $this->assertSame('已隐藏', $report->resolution_note);

        // Closing an already closed report is a user mistake: the page comes
        // back with the reason instead of an error page.
        $this->from(route('moderation.reports'))
            ->actingAs($moderator)
            ->post(route('moderation.reports.dismiss', $report))
            ->assertRedirect(route('moderation.reports'))
            ->assertSessionHas('error', __('forum.errors.report_already_handled'));
    }

    #[Test]
    public function a_member_cannot_close_a_report(): void
    {
        $report = Report::factory()->create();

        $this->actingAs($this->member())
            ->post(route('moderation.reports.dismiss', $report))
            ->assertForbidden();

        $this->assertTrue($report->refresh()->isOpen());
    }

    #[Test]
    public function a_moderator_can_hide_and_unhide_a_comment_from_the_thread(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);
        $post->increment('comment_count');
        $moderator = $this->moderator();

        $this->actingAs($moderator)
            ->post(route('moderation.comments.hide', $comment))
            ->assertRedirect();

        $this->assertSame(Comment::STATUS_HIDDEN, $comment->refresh()->status);
        $this->assertSame(0, $post->refresh()->comment_count);

        $this->actingAs($moderator)
            ->delete(route('moderation.comments.unhide', $comment))
            ->assertRedirect();

        $this->assertSame(Comment::STATUS_VISIBLE, $comment->refresh()->status);
        $this->assertSame(1, $post->refresh()->comment_count);
    }

    #[Test]
    public function a_member_cannot_hide_someone_elses_comment(): void
    {
        $comment = Comment::factory()->create();

        $this->actingAs($this->member())
            ->post(route('moderation.comments.hide', $comment))
            ->assertForbidden();

        $this->assertSame(Comment::STATUS_VISIBLE, $comment->refresh()->status);
    }

    #[Test]
    public function the_post_toolbar_offers_the_moderator_actions(): void
    {
        $post = Post::factory()->create(['title' => '带工具条的帖子']);
        Category::factory()->create(['name' => '漏洞分析']);

        $this->actingAs($this->moderator())
            ->get(route('forum.show', $post))
            ->assertOk()
            ->assertSee(__('moderation_ui.post.toolbar'))
            ->assertSee(__('moderation_ui.post.feature'))
            ->assertSee(__('moderation_ui.post.lock'))
            ->assertSee(__('moderation_ui.post.move'))
            ->assertSee('漏洞分析');
    }

    #[Test]
    public function an_ordinary_member_does_not_see_the_toolbar(): void
    {
        $post = Post::factory()->create();

        $this->actingAs($this->member())
            ->get(route('forum.show', $post))
            ->assertOk()
            ->assertDontSee(__('moderation_ui.post.toolbar'));
    }

    #[Test]
    public function a_moderator_features_locks_moves_and_pins_a_post_from_the_page(): void
    {
        $from = Category::factory()->create(['name' => '公告']);
        $to = Category::factory()->create(['name' => '逆向工程']);
        $post = Post::factory()->create(['category_id' => $from->id]);
        $moderator = $this->moderator();

        $this->actingAs($moderator)->post(route('moderation.forum.feature', $post))->assertRedirect();
        $this->assertTrue($post->refresh()->is_featured);

        $this->actingAs($moderator)->post(route('moderation.forum.lock', $post))->assertRedirect();
        $this->assertTrue($post->refresh()->is_locked);

        $this->actingAs($moderator)
            ->put(route('moderation.forum.move', $post), ['category_id' => $to->id])
            ->assertRedirect();
        $this->assertSame($to->id, $post->refresh()->category_id);

        $this->actingAs($moderator)->post(route('moderation.forum.pin', $post))->assertRedirect();
        $this->assertTrue($post->refresh()->is_pinned);

        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.post.featured']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.post.locked']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.post.moved']);
    }

    #[Test]
    public function a_member_cannot_use_the_moderator_actions(): void
    {
        $post = Post::factory()->create();
        $member = $this->member();

        $this->actingAs($member)->post(route('moderation.forum.feature', $post))->assertForbidden();
        $this->actingAs($member)->post(route('moderation.forum.lock', $post))->assertForbidden();
        $this->actingAs($member)->put(route('moderation.forum.move', $post), ['category_id' => null])
            ->assertForbidden();

        $post->refresh();
        $this->assertFalse($post->is_featured);
        $this->assertFalse($post->is_locked);
    }

    #[Test]
    public function a_locked_thread_hides_the_comment_form(): void
    {
        $post = Post::factory()->create(['is_locked' => true]);

        $this->actingAs($this->member())
            ->get(route('forum.show', $post))
            ->assertOk()
            ->assertSee(__('moderation_ui.post.locked_notice'))
            ->assertDontSee(__('forum_ui.post_comment'));

        $this->actingAs($this->member())
            ->post(route('forum.comments.store', $post), ['content' => '还要回'])
            ->assertForbidden();
    }

    #[Test]
    public function a_member_reports_a_post_from_the_page(): void
    {
        $post = Post::factory()->create();
        $reporter = $this->member();

        $this->actingAs($reporter)
            ->post(route('forum.report', $post), ['reason' => 'spam', 'detail' => '广告贴'])
            ->assertRedirect()
            ->assertSessionHas('status', __('forum.messages.report_created'));

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reportable_id' => $post->id,
            'reason' => 'spam',
        ]);

        // Reporting again comes back as a friendly message, not a 409 page.
        $this->actingAs($reporter)
            ->post(route('forum.report', $post), ['reason' => 'abuse'])
            ->assertRedirect()
            ->assertSessionHas('error', __('forum.errors.report_duplicate'));
    }

    #[Test]
    public function a_member_reports_a_comment_from_the_page(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);

        $this->actingAs($this->member())
            ->post(route('forum.comments.report', [$post, $comment]), ['reason' => 'abuse'])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reportable_type' => $comment->getMorphClass(),
            'reportable_id' => $comment->id,
        ]);
    }

    #[Test]
    public function a_member_cannot_report_their_own_post(): void
    {
        $author = $this->member();
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->actingAs($author)
            ->post(route('forum.report', $post), ['reason' => 'spam'])
            ->assertRedirect()
            ->assertSessionHas('error', __('forum.errors.report_own'));

        $this->assertDatabaseCount('reports', 0);
    }

    #[Test]
    public function the_page_marks_content_the_member_already_reported(): void
    {
        $post = Post::factory()->create();
        $reporter = $this->member();

        $this->actingAs($reporter)
            ->get(route('forum.show', $post))
            ->assertOk()
            ->assertSee(__('moderation_ui.report.action'));

        $this->actingAs($reporter)->post(route('forum.report', $post), ['reason' => 'spam']);

        $this->actingAs($reporter)
            ->get(route('forum.show', $post))
            ->assertOk()
            ->assertSee(__('moderation_ui.report.already'));
    }

    #[Test]
    public function the_trash_lists_and_restores_own_content(): void
    {
        $author = $this->member();
        $post = Post::factory()->create(['author_id' => $author->id, 'title' => '我删掉的帖子']);
        $post->delete();

        $this->actingAs($author)
            ->get('/moderation/trash')
            ->assertOk()
            ->assertSee('我删掉的帖子')
            ->assertSee(__('moderation_ui.trash.subtitle_own'));

        $this->actingAs($author)
            ->post(route('moderation.trash.posts.restore', $post->id))
            ->assertRedirect();

        $this->assertNull($post->refresh()->deleted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.post.restored']);
    }

    #[Test]
    public function the_trash_restores_a_deleted_comment_and_its_counter(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id, 'content' => '被删的评论']);
        $post->increment('comment_count');

        $moderator = $this->moderator();

        // Delete through the service so the counter behaves as in production.
        app(CommentService::class)->delete($moderator, $comment);
        $this->assertSame(0, $post->refresh()->comment_count);

        $this->actingAs($moderator)
            ->get('/moderation/trash')
            ->assertOk()
            ->assertSee('被删的评论')
            ->assertSee(__('moderation_ui.trash.subtitle_all'));

        $this->actingAs($moderator)
            ->post(route('moderation.trash.comments.restore', $comment->id))
            ->assertRedirect();

        $this->assertNull($comment->refresh()->deleted_at);
        $this->assertSame(1, $post->refresh()->comment_count);
    }

    #[Test]
    public function the_trash_cannot_be_used_to_restore_someone_elses_content(): void
    {
        $author = $this->member();
        $post = Post::factory()->create(['author_id' => $author->id]);
        $post->delete();

        $this->actingAs($this->member())
            ->post(route('moderation.trash.posts.restore', $post->id))
            ->assertForbidden();

        $this->assertNotNull($post->refresh()->deleted_at);
    }

    #[Test]
    public function the_navigation_only_shows_governance_links_to_moderators(): void
    {
        Report::factory()->create();

        $this->actingAs($this->moderator())
            ->get('/forum')
            ->assertOk()
            ->assertSee(__('moderation_ui.nav.moderation'));

        $this->actingAs($this->member())
            ->get('/forum')
            ->assertOk()
            ->assertDontSee(__('moderation_ui.nav.moderation'));
    }
}
