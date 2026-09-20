<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\ErrorCode;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The mailbox gate has a local escape hatch for machines that cannot receive
 * mail. These cases pin down exactly how far that hatch opens.
 */
class VerifiedGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function unverifiedMember(): User
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('user');

        return $user;
    }

    #[Test]
    public function the_gate_is_on_by_default(): void
    {
        $this->assertTrue((bool) config('noircat.auth.require_verified_email'));

        $this->actingAs($this->unverifiedMember())
            ->post('/forum', ['title' => '标题', 'content' => '正文'])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseCount('posts', 0);
    }

    #[Test]
    public function it_can_be_switched_off_locally(): void
    {
        config(['noircat.auth.require_verified_email' => false]);

        $category = \App\Models\Category::factory()->create();

        $this->actingAs($this->unverifiedMember())
            ->post('/forum', [
                'title' => '本地无邮件通道也能发帖',
                'content' => '正文内容',
                'category_id' => $category->id,
                'status' => Post::STATUS_PUBLISHED,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', ['title' => '本地无邮件通道也能发帖']);
    }

    #[Test]
    public function the_api_answers_with_the_envelope_while_the_gate_is_off(): void
    {
        config(['noircat.auth.require_verified_email' => false]);

        $user = $this->unverifiedMember();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/posts', ['title' => '标题', 'content' => '正文'])
            ->assertCreated();
    }

    #[Test]
    public function production_ignores_the_switch(): void
    {
        // Same configuration a copied .env could contain, but a deployment must
        // never accept unverified writers.
        config(['noircat.auth.require_verified_email' => false]);
        $this->app['env'] = 'production';

        $this->assertTrue($this->app->isProduction());

        // CSRF is only bypassed while the environment is "testing", so the
        // browser-side case skips that middleware explicitly.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->actingAs($this->unverifiedMember())
            ->post('/forum', ['title' => '标题', 'content' => '正文'])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseCount('posts', 0);

        // The API keeps answering with the documented error code.
        $this->app['auth']->forgetGuards();
        $user = $this->unverifiedMember();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/posts', ['title' => '标题', 'content' => '正文'])
            ->assertStatus(403)
            ->assertJsonPath('code', ErrorCode::EMAIL_NOT_VERIFIED->value);
    }
}
