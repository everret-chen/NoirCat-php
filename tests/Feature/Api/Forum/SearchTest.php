<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Forum;

use App\Enums\ErrorCode;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_titles_and_bodies(): void
    {
        Post::factory()->create(['title' => 'XSS 防御要点']);
        Post::factory()->create(['title' => '无关帖', 'content' => '这里讲了 SQL 注入的检测']);
        Post::factory()->create(['title' => '完全无关']);

        $byTitle = $this->getJson('/api/search?q=XSS')->assertOk();
        $this->assertSame(1, $byTitle->json('meta.total'));

        $byBody = $this->getJson('/api/search?q='.urlencode('SQL 注入'))->assertOk();
        $this->assertSame(1, $byBody->json('meta.total'));
    }

    public function test_search_does_not_treat_input_as_a_wildcard_pattern(): void
    {
        Post::factory()->count(3)->create();

        // "%" must be matched literally, not as "match everything".
        $this->getJson('/api/search?q='.urlencode('%%'))->assertOk()->assertJsonPath('meta.total', 0);

        $this->getJson('/api/search?q='.urlencode('__'))->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_a_term_containing_a_wildcard_still_finds_its_post(): void
    {
        // Regression: escaping wildcards with a bare backslash made these
        // terms unsearchable on SQLite, which has no default LIKE escape.
        Post::factory()->create(['title' => '100% 覆盖率怎么写']);
        Post::factory()->create(['title' => 'a_b 命名规范']);
        Post::factory()->create(['title' => '无关帖']);

        $this->getJson('/api/search?q='.urlencode('100%'))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/search?q='.urlencode('a_b'))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/search?q='.urlencode('100% 覆盖'))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_search_requires_a_minimum_query_length(): void
    {
        $this->getJson('/api/search?q=a')
            ->assertStatus(422)
            ->assertJsonPath('code', ErrorCode::VALIDATION_FAILED->value);

        $this->getJson('/api/search')
            ->assertStatus(422);
    }

    public function test_search_only_returns_published_posts(): void
    {
        Post::factory()->create(['title' => 'published needle']);
        Post::factory()->draft()->create(['title' => 'draft needle']);

        $this->getJson('/api/search?q=needle')->assertOk()->assertJsonPath('meta.total', 1);
    }
}
