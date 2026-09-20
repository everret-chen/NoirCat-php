<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarkdownService;
use Tests\TestCase;

/**
 * The renderer is the boundary between user supplied Markdown and the HTML we
 * serve, so these cases are security tests rather than formatting tests.
 */
class MarkdownServiceTest extends TestCase
{
    private MarkdownService $markdown;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markdown = app(MarkdownService::class);
    }

    public function test_it_renders_common_markdown(): void
    {
        $html = $this->markdown->toHtml("# 标题\n\n**加粗** 与 `代码`\n\n- 一\n- 二");

        $this->assertStringContainsString('<h1>标题</h1>', $html);
        $this->assertStringContainsString('<strong>加粗</strong>', $html);
        $this->assertStringContainsString('<code>代码</code>', $html);
        $this->assertStringContainsString('<li>一</li>', $html);
    }

    public function test_raw_html_is_stripped(): void
    {
        $html = $this->markdown->toHtml('hello <script>alert(1)</script> <b onclick="evil()">bold</b>');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('</script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<b ', $html);
        // The payload may survive as inert text; what matters is that it is
        // never markup a browser would execute.
        $this->assertStringContainsString('alert(1)', $html);
    }

    public function test_unsafe_link_schemes_are_removed(): void
    {
        $html = $this->markdown->toHtml('[click](javascript:alert(1)) [data](data:text/html;base64,PHNjcmlwdD4=)');

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('data:text/html', $html);
    }

    public function test_images_cannot_carry_event_handlers(): void
    {
        $html = $this->markdown->toHtml('<img src="x" onerror="alert(1)"> ![ok](https://example.com/a.png)');

        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('https://example.com/a.png', $html);
    }

    public function test_style_and_iframe_payloads_are_dropped(): void
    {
        $html = $this->markdown->toHtml('<iframe src="https://evil.test"></iframe><style>body{display:none}</style>');

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<style', $html);
    }

    public function test_excerpt_returns_plain_text(): void
    {
        $excerpt = $this->markdown->excerpt("# 标题\n\n**加粗** 正文内容", 20);

        $this->assertStringNotContainsString('<', $excerpt);
        $this->assertStringContainsString('标题', $excerpt);
    }
}
