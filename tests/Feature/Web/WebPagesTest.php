<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Covers the Blade entry point: the pages must render, the CSRF protection must
 * hold, and the write endpoints must apply the same rules as the API because
 * both go through the same services.
 */
class WebPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function member(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('user');

        return $user;
    }

    public function test_the_public_pages_render(): void
    {
        $category = Category::factory()->create(['name' => '公告']);

        $this->get('/')->assertOk()->assertSee('NoirCat');
        $this->get('/forum')->assertOk()->assertSee('公告');
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_missing_pages_answer_404(): void
    {
        $this->get('/forum/999999')->assertNotFound();
        $this->get('/not-a-real-page')->assertNotFound();
    }

    public function test_guests_are_redirected_to_the_sign_in_page(): void
    {
        $this->get('/forum/create')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/sessions')->assertRedirect('/login');
    }

    /**
     * CSRF is bypassed while the test environment runs (VerifyCsrfToken skips
     * unit tests), so the token check is exercised over real HTTP in
     * .tmp/smoke-web.php instead: a POST without _token must answer 419.
     */
    public function test_a_guest_cannot_reach_the_write_endpoints(): void
    {
        $this->post('/forum', ['title' => 'x', 'content' => 'y'])->assertRedirect('/login');
    }

    public function test_registration_signs_the_new_member_in(): void
    {
        $response = $this->post('/register', [
            'username' => 'newcomer',
            'email' => 'newcomer@example.com',
            'password' => 'S3cret-pass',
            'password_confirmation' => 'S3cret-pass',
        ]);

        $response->assertRedirect(route('forum.index'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['username' => 'newcomer']);
    }

    public function test_the_sign_in_form_accepts_valid_credentials(): void
    {
        $this->member(['username' => 'reader', 'password' => Hash::make('S3cret-pass')]);

        $this->post('/login', ['username' => 'reader', 'password' => 'S3cret-pass'])
            ->assertRedirect(route('forum.index'));

        $this->assertAuthenticated();
    }

    public function test_the_sign_in_form_rejects_a_bad_password(): void
    {
        $this->member(['username' => 'reader', 'password' => Hash::make('S3cret-pass')]);

        $this->from('/login')
            ->post('/login', ['username' => 'reader', 'password' => 'wrong-pass-1'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_a_signed_link_verifies_the_address_and_redirects_to_a_page(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        // No session yet: the link is normally clicked from a mail client.
        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_an_unsigned_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get("/email/verify/{$user->id}/".sha1($user->getEmailForVerification()))
            ->assertStatus(403);

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_an_unverified_member_cannot_write(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/forum/create')->assertRedirect(route('profile.edit'));
        $this->actingAs($user)->post('/forum', [
            'title' => '草稿标题',
            'content' => '正文内容',
        ])->assertRedirect(route('profile.edit'));

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_a_verified_member_can_publish_and_read_back_a_post(): void
    {
        $user = $this->member();
        $category = Category::factory()->create(['name' => '公告']);

        $response = $this->actingAs($user)->post('/forum', [
            'title' => 'SQL 注入入门',
            'content' => "## 小标题\n\n**加粗**内容\n\n<script>alert('xss')</script>",
            'category_id' => $category->id,
            'status' => Post::STATUS_PUBLISHED,
        ]);

        $post = Post::query()->firstOrFail();
        $response->assertRedirect(route('forum.show', $post));

        $this->actingAs($user)
            ->get(route('forum.show', $post))
            ->assertOk()
            ->assertSee('SQL 注入入门')
            ->assertSee('<strong>加粗</strong>', false)
            // The raw script tag must never reach the browser.
            ->assertDontSee('<script>alert', false);
    }

    public function test_the_author_can_update_and_delete_a_post(): void
    {
        $user = $this->member();
        $post = Post::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)
            ->put(route('forum.update', $post), [
                'title' => '修订后的标题',
                'content' => '修订后的正文',
                'status' => Post::STATUS_PUBLISHED,
            ])
            ->assertRedirect(route('forum.show', $post));

        $this->assertSame('修订后的标题', $post->refresh()->title);

        $this->actingAs($user)
            ->delete(route('forum.destroy', $post))
            ->assertRedirect(route('forum.index'));

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_a_member_cannot_edit_someone_elses_post(): void
    {
        $author = $this->member();
        $other = $this->member(['username' => 'intruder']);
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->actingAs($other)->get(route('forum.edit', $post))->assertForbidden();
        $this->actingAs($other)->put(route('forum.update', $post), [
            'title' => '被篡改的标题',
            'content' => '被篡改的正文',
        ])->assertForbidden();
    }

    public function test_a_comment_is_stored_as_plain_text(): void
    {
        $user = $this->member();
        $post = Post::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('forum.comments.store', $post), ['content' => '<b>粗体</b> 与 "引号"'])
            ->assertRedirect();

        $comment = Comment::query()->firstOrFail();
        $this->assertSame('<b>粗体</b> 与 "引号"', $comment->content);

        $this->actingAs($user)
            ->get(route('forum.show', $post))
            ->assertOk()
            ->assertSee('&lt;b&gt;粗体&lt;/b&gt;', false);
    }

    public function test_a_comment_cannot_be_attached_to_a_draft(): void
    {
        $author = $this->member();
        $draft = Post::factory()->draft()->create(['author_id' => $author->id]);

        $this->actingAs($author)
            ->post(route('forum.comments.store', $draft), ['content' => '草稿评论'])
            ->assertForbidden();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_liking_toggles_from_the_post_page(): void
    {
        $user = $this->member();
        $post = Post::factory()->create(['author_id' => $user->id]);

        $this->actingAs($user)->post(route('forum.like', $post))->assertRedirect();
        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likeable_type' => $post->getMorphClass(),
            'likeable_id' => $post->id,
        ]);

        $this->actingAs($user)->delete(route('forum.unlike', $post))->assertRedirect();
        $this->assertDatabaseMissing('likes', [
            'user_id' => $user->id,
            'likeable_type' => $post->getMorphClass(),
            'likeable_id' => $post->id,
        ]);
    }

    public function test_the_account_pages_render_for_the_owner(): void
    {
        $user = $this->member(['email' => 'owner@example.com']);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('owner@example.com');
        $this->actingAs($user)->get('/sessions')->assertOk();
    }

    public function test_the_profile_can_be_updated_from_the_page(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->put('/profile', [
                'username' => $user->username,
                'email' => 'changed@example.com',
                'bio' => '网络安全学习者',
            ])
            ->assertRedirect();

        $this->assertSame('changed@example.com', $user->refresh()->email);
    }

    public function test_logging_out_ends_the_session(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();

        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_the_locale_can_be_switched_from_the_query_string(): void
    {
        $this->get('/forum?lang=en')->assertOk()->assertSee('lang="en"', false);
        $this->get('/forum?lang=../etc/passwd')->assertOk();
    }
}
