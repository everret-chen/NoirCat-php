<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Forum;

use App\Enums\ErrorCode;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
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

    public function test_a_member_can_publish_a_post_and_the_html_is_sanitised(): void
    {
        $user = $this->member();
        $category = Category::factory()->create(['type' => Category::TYPE_POST]);

        $response = $this->withToken($this->tokenFor($user))->postJson('/api/posts', [
            'title' => 'Hello NoirCat',
            'content' => "# 标题\n\n**加粗**\n\n<script>alert('xss')</script>",
            'category_id' => $category->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Hello NoirCat')
            ->assertJsonPath('data.status', Post::STATUS_PUBLISHED);

        $post = Post::query()->firstOrFail();

        $this->assertSame($user->id, $post->author_id);
        $this->assertStringContainsString('<strong>加粗</strong>', (string) $post->content_html);
        $this->assertStringNotContainsString('<script', (string) $post->content_html);
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.post.created']);
    }

    public function test_the_author_cannot_be_forged_through_the_request_body(): void
    {
        $user = $this->member();
        $victim = User::factory()->create();

        $this->withToken($this->tokenFor($user))->postJson('/api/posts', [
            'title' => 'Forged author',
            'content' => 'body',
            'author_id' => $victim->id,
        ])->assertCreated();

        $this->assertSame($user->id, Post::query()->firstOrFail()->author_id);
    }

    public function test_guests_cannot_create_posts(): void
    {
        $this->postJson('/api/posts', ['title' => 'nope', 'content' => 'nope'])
            ->assertStatus(401)
            ->assertJsonPath('code', ErrorCode::UNAUTHENTICATED->value);
    }

    public function test_drafts_are_hidden_from_the_public_list(): void
    {
        Post::factory()->create(['title' => 'published one']);
        Post::factory()->draft()->create(['title' => 'draft one']);

        $response = $this->getJson('/api/posts')->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->all();

        $this->assertContains('published one', $titles);
        $this->assertNotContains('draft one', $titles);
    }

    public function test_a_draft_is_only_visible_to_its_author_and_moderators(): void
    {
        $author = $this->member();
        $draft = Post::factory()->draft()->create(['author_id' => $author->id]);

        $this->withToken($this->tokenFor($author))->getJson('/api/posts/'.$draft->id)->assertOk();

        $stranger = $this->member();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($stranger))->getJson('/api/posts/'.$draft->id)->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($this->moderator()))->getJson('/api/posts/'.$draft->id)->assertOk();
    }

    public function test_views_are_counted_once_per_visitor(): void
    {
        $post = Post::factory()->create();

        $this->getJson('/api/posts/'.$post->id)->assertOk();
        $this->getJson('/api/posts/'.$post->id)->assertOk();

        $this->assertSame(1, $post->refresh()->view_count);
    }

    public function test_only_the_author_or_a_moderator_can_update_a_post(): void
    {
        $author = $this->member();
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->withToken($this->tokenFor($author))->putJson('/api/posts/'.$post->id, ['title' => 'edited'])
            ->assertOk()
            ->assertJsonPath('data.title', 'edited');

        $stranger = $this->member();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($stranger))->putJson('/api/posts/'.$post->id, ['title' => 'hacked'])
            ->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($this->moderator()))->putJson('/api/posts/'.$post->id, ['title' => 'moderated'])
            ->assertOk();

        $this->assertSame('moderated', $post->refresh()->title);
    }

    public function test_only_the_author_or_a_moderator_can_delete_a_post(): void
    {
        $author = $this->member();
        $post = Post::factory()->create(['author_id' => $author->id]);

        $stranger = $this->member();
        $this->withToken($this->tokenFor($stranger))->deleteJson('/api/posts/'.$post->id)->assertStatus(403);
        $this->assertNull($post->refresh()->deleted_at);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($author))->deleteJson('/api/posts/'.$post->id)->assertOk();
        $this->assertNotNull($post->refresh()->deleted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.post.deleted']);
    }

    public function test_only_a_moderator_can_pin_a_post(): void
    {
        $post = Post::factory()->create();
        $member = $this->member();

        $this->withToken($this->tokenFor($member))->postJson('/api/posts/'.$post->id.'/pin')->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($this->moderator()))->postJson('/api/posts/'.$post->id.'/pin')
            ->assertOk()
            ->assertJsonPath('data.is_pinned', true);

        $this->assertTrue($post->refresh()->is_pinned);
    }

    public function test_liking_twice_does_not_inflate_the_counter(): void
    {
        $post = Post::factory()->create();
        $user = $this->member();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/posts/'.$post->id.'/like')->assertOk();
        $this->withToken($token)->postJson('/api/posts/'.$post->id.'/like')->assertOk();

        $this->assertSame(1, $post->refresh()->like_count);
        $this->assertDatabaseCount('likes', 1);

        $this->withToken($token)->deleteJson('/api/posts/'.$post->id.'/like')->assertOk();
        $this->assertSame(0, $post->refresh()->like_count);
    }

    public function test_the_list_can_be_filtered_by_category_and_searched(): void
    {
        $security = Category::factory()->create(['slug' => 'security', 'type' => Category::TYPE_POST]);
        $general = Category::factory()->create(['slug' => 'general', 'type' => Category::TYPE_POST]);

        Post::factory()->create(['category_id' => $security->id, 'title' => 'SQL injection notes']);
        Post::factory()->create(['category_id' => $general->id, 'title' => 'Random chat']);

        $filtered = $this->getJson('/api/posts?category=security')->assertOk();
        $this->assertSame(1, $filtered->json('meta.total'));

        $searched = $this->getJson('/api/posts?q=injection')->assertOk();
        $this->assertSame(1, $searched->json('meta.total'));
    }
}
