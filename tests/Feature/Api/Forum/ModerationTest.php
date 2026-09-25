<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Forum;

use App\Enums\ErrorCode;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Moderator actions: what a moderator may do to content they do not own, and
 * what an ordinary member must not be able to do.
 */
class ModerationTest extends TestCase
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

    private function tokenFor(User $user): string
    {
        return $user->createToken('phpunit')->plainTextToken;
    }

    #[Test]
    public function a_moderator_can_feature_and_unfeature_a_post(): void
    {
        $post = Post::factory()->create();
        $token = $this->tokenFor($this->moderator());

        $this->withToken($token)
            ->postJson('/api/posts/'.$post->id.'/feature')
            ->assertOk()
            ->assertJsonPath('data.is_featured', true);

        $this->assertTrue($post->refresh()->is_featured);
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.post.featured']);

        $this->withToken($token)
            ->postJson('/api/posts/'.$post->id.'/feature', ['featured' => false])
            ->assertOk()
            ->assertJsonPath('data.is_featured', false);

        $this->assertFalse($post->refresh()->is_featured);
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.post.unfeatured']);
    }

    #[Test]
    public function a_member_cannot_feature_lock_or_move(): void
    {
        $post = Post::factory()->create();
        $token = $this->tokenFor($this->member());

        $this->withToken($token)->postJson('/api/posts/'.$post->id.'/feature')
            ->assertStatus(403)->assertJsonPath('code', ErrorCode::FORBIDDEN->value);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/posts/'.$post->id.'/lock')
            ->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->putJson('/api/posts/'.$post->id.'/category', ['category_id' => null])
            ->assertStatus(403);

        $this->assertFalse($post->refresh()->is_featured);
        $this->assertFalse($post->refresh()->is_locked);
    }

    #[Test]
    public function a_locked_thread_stops_accepting_comments(): void
    {
        $post = Post::factory()->create();
        $moderatorToken = $this->tokenFor($this->moderator());

        $this->withToken($moderatorToken)->postJson('/api/posts/'.$post->id.'/lock')->assertOk();
        $this->assertTrue($post->refresh()->is_locked);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($this->member()))
            ->postJson('/api/posts/'.$post->id.'/comments', ['content' => '还要回帖'])
            ->assertStatus(403)
            ->assertJsonPath('code', ErrorCode::FORBIDDEN->value);

        $this->assertDatabaseCount('comments', 0);

        // Unlocking restores normal behaviour.
        $this->app['auth']->forgetGuards();
        $this->withToken($moderatorToken)->postJson('/api/posts/'.$post->id.'/lock', ['locked' => false])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($this->member()))
            ->postJson('/api/posts/'.$post->id.'/comments', ['content' => '现在可以了'])
            ->assertCreated();
    }

    #[Test]
    public function a_moderator_can_move_a_post_to_another_section(): void
    {
        $from = Category::factory()->create(['name' => '公告']);
        $to = Category::factory()->create(['name' => '漏洞分析']);
        $post = Post::factory()->create(['category_id' => $from->id]);

        $this->withToken($this->tokenFor($this->moderator()))
            ->putJson('/api/posts/'.$post->id.'/category', ['category_id' => $to->id])
            ->assertOk()
            ->assertJsonPath('data.category.name', '漏洞分析');

        $this->assertSame($to->id, $post->refresh()->category_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.post.moved']);

        // Moving to "no section" is allowed too.
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($this->moderator()))
            ->putJson('/api/posts/'.$post->id.'/category', ['category_id' => null])
            ->assertOk();

        $this->assertNull($post->refresh()->category_id);
    }

    #[Test]
    public function trash_lists_a_members_own_deletions_only(): void
    {
        $author = $this->member();
        $other = $this->member();

        $mine = Post::factory()->create(['author_id' => $author->id, 'title' => '我删的帖子']);
        $theirs = Post::factory()->create(['author_id' => $other->id, 'title' => '别人删的帖子']);
        $mine->delete();
        $theirs->delete();

        $response = $this->withToken($this->tokenFor($author))->getJson('/api/moderation/trash')->assertOk();

        $titles = collect($response->json('data.posts'))->pluck('title')->all();

        $this->assertContains('我删的帖子', $titles);
        $this->assertNotContains('别人删的帖子', $titles);
    }

    #[Test]
    public function a_moderator_sees_everyones_deletions(): void
    {
        $author = $this->member();
        $post = Post::factory()->create(['author_id' => $author->id, 'title' => '别人的帖子']);
        $post->delete();

        $response = $this->withToken($this->tokenFor($this->moderator()))
            ->getJson('/api/moderation/trash')
            ->assertOk();

        $this->assertContains('别人的帖子', collect($response->json('data.posts'))->pluck('title')->all());
    }

    #[Test]
    public function an_author_can_restore_their_own_post_but_not_someone_elses(): void
    {
        $author = $this->member();
        $intruder = $this->member();

        $post = Post::factory()->create(['author_id' => $author->id]);
        $post->delete();

        $this->withToken($this->tokenFor($intruder))
            ->postJson('/api/posts/'.$post->id.'/restore')
            ->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($author))
            ->postJson('/api/posts/'.$post->id.'/restore')
            ->assertOk();

        $this->assertNull($post->refresh()->deleted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.post.restored']);
    }

    #[Test]
    public function hidden_and_deleted_comments_can_be_brought_back(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);
        $post->increment('comment_count');
        $token = $this->tokenFor($this->moderator());

        $this->withToken($token)->postJson('/api/comments/'.$comment->id.'/hide')->assertOk();
        $this->assertSame(0, $post->refresh()->comment_count);

        $this->withToken($token)->postJson('/api/comments/'.$comment->id.'/unhide')->assertOk();
        $this->assertSame(Comment::STATUS_VISIBLE, $comment->refresh()->status);
        $this->assertSame(1, $post->refresh()->comment_count);

        // Hiding twice is a conflict, not a silent second success.
        $this->withToken($token)->postJson('/api/comments/'.$comment->id.'/hide')->assertOk();
        $this->withToken($token)->postJson('/api/comments/'.$comment->id.'/unhide')->assertOk();
        $this->assertSame(1, $post->refresh()->comment_count);

        $this->withToken($token)->deleteJson('/api/comments/'.$comment->id)->assertOk();
        $this->assertSame(0, $post->refresh()->comment_count);

        $this->withToken($token)->postJson('/api/comments/'.$comment->id.'/restore')->assertOk();
        $this->assertNull($comment->refresh()->deleted_at);
        $this->assertSame(1, $post->refresh()->comment_count);
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.comment.restored']);
    }

    #[Test]
    public function a_member_cannot_restore_a_comment_they_do_not_own(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);
        $comment->delete();

        $this->withToken($this->tokenFor($this->member()))
            ->postJson('/api/comments/'.$comment->id.'/restore')
            ->assertStatus(403);

        $this->assertNotNull($comment->refresh()->deleted_at);
    }

    #[Test]
    public function moderator_endpoints_require_authentication(): void
    {
        $post = Post::factory()->create();

        $this->postJson('/api/posts/'.$post->id.'/feature')->assertStatus(401);
        $this->getJson('/api/moderation/trash')->assertStatus(401);
        $this->getJson('/api/reports')->assertStatus(401);
    }
}
