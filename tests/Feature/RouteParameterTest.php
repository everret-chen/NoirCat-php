<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every route parameter that addresses a row by id is digits only.
 *
 * This is not cosmetic: MySQL coerces the string "12abc" to 12 when comparing
 * it against a BIGINT key, so without the pattern /forum/12abc would serve post
 * 12 there while SQLite answers 404 - the same URL exposing different content
 * depending on the database. The route patterns in AppServiceProvider keep the
 * two engines in agreement.
 */
class RouteParameterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_post_id_with_trailing_letters_is_not_a_route(): void
    {
        $post = Post::factory()->create();

        $this->get('/forum/'.$post->id.'abc')->assertNotFound();
        $this->getJson('/api/posts/'.$post->id.'abc')->assertNotFound();
    }

    #[Test]
    public function a_comment_id_with_trailing_letters_is_not_a_route(): void
    {
        $comment = Comment::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson('/api/comments/'.$comment->id.'abc')
            ->assertNotFound();

        $this->assertNull($comment->refresh()->deleted_at);
    }

    #[Test]
    public function a_session_id_with_trailing_letters_is_not_a_route(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/auth/sessions/'.$token->accessToken->id.'abc')
            ->assertNotFound();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function a_signed_verification_link_with_a_lettered_id_is_not_a_route(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get('/email/verify/'.(string) $user->id.'abc/'.sha1($user->getEmailForVerification()))
            ->assertNotFound();

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    #[Test]
    public function the_numeric_forms_still_work(): void
    {
        $post = Post::factory()->create();

        $this->get('/forum/'.$post->id)->assertOk();
        $this->getJson('/api/posts/'.$post->id)->assertOk();
        $this->getJson('/api/posts/'.$post->id.'/comments')->assertOk();
    }
}
