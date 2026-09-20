<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Forum;

use App\Enums\ErrorCode;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
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

    public function test_a_member_can_comment_and_the_counter_is_maintained(): void
    {
        $post = Post::factory()->create();
        $user = $this->member();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/posts/'.$post->id.'/comments', ['content' => '第一条评论'])
            ->assertCreated()
            ->assertJsonPath('data.content', '第一条评论');

        $this->assertSame(1, $post->refresh()->comment_count);
        $this->assertNotNull($post->last_commented_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.comment.created']);
    }

    public function test_guests_cannot_comment(): void
    {
        $post = Post::factory()->create();

        $this->postJson('/api/posts/'.$post->id.'/comments', ['content' => 'nope'])
            ->assertStatus(401)
            ->assertJsonPath('code', ErrorCode::UNAUTHENTICATED->value);
    }

    public function test_a_reply_to_a_comment_of_another_post_is_rejected(): void
    {
        $post = Post::factory()->create();
        $otherPost = Post::factory()->create();
        $foreign = Comment::factory()->create(['post_id' => $otherPost->id]);

        $this->withToken($this->tokenFor($this->member()))
            ->postJson('/api/posts/'.$post->id.'/comments', [
                'content' => 'sneaky reply',
                'parent_id' => $foreign->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', ErrorCode::VALIDATION_FAILED->value);

        $this->assertSame(0, $post->refresh()->comment_count);
    }

    public function test_nesting_beyond_the_depth_limit_is_rejected(): void
    {
        $post = Post::factory()->create();
        $token = $this->tokenFor($this->member());

        $parentId = null;

        for ($level = 1; $level <= 3; $level++) {
            $response = $this->withToken($token)->postJson('/api/posts/'.$post->id.'/comments', [
                'content' => 'level '.$level,
                'parent_id' => $parentId,
            ])->assertCreated();

            $parentId = $response->json('data.id');
        }

        $this->withToken($token)->postJson('/api/posts/'.$post->id.'/comments', [
            'content' => 'level 4',
            'parent_id' => $parentId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', ErrorCode::BUSINESS_RULE_VIOLATION->value);
    }

    public function test_only_the_author_or_a_moderator_can_delete_a_comment(): void
    {
        $comment = Comment::factory()->create();
        $post = $comment->post;

        $stranger = $this->member();
        $this->withToken($this->tokenFor($stranger))->deleteJson('/api/comments/'.$comment->id)->assertStatus(403);
        $this->assertNull($comment->refresh()->deleted_at);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($this->moderator()))->deleteJson('/api/comments/'.$comment->id)->assertOk();

        $this->assertNotNull($comment->refresh()->deleted_at);
    }

    public function test_a_moderator_can_hide_a_comment(): void
    {
        $comment = Comment::factory()->create();
        $member = $this->member();

        $this->withToken($this->tokenFor($member))->postJson('/api/comments/'.$comment->id.'/hide')->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($this->moderator()))->postJson('/api/comments/'.$comment->id.'/hide')
            ->assertOk()
            ->assertJsonPath('data.status', Comment::STATUS_HIDDEN);

        $this->assertSame(Comment::STATUS_HIDDEN, $comment->refresh()->status);
    }

    public function test_hidden_comments_are_not_listed(): void
    {
        $post = Post::factory()->create();
        Comment::factory()->create(['post_id' => $post->id, 'content' => 'visible one']);
        Comment::factory()->hidden()->create(['post_id' => $post->id, 'content' => 'hidden one']);

        $response = $this->getJson('/api/posts/'.$post->id.'/comments')->assertOk();

        $contents = collect($response->json('data'))->pluck('content')->all();

        $this->assertContains('visible one', $contents);
        $this->assertNotContains('hidden one', $contents);
    }
}
