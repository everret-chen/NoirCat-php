<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MailLogReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The parser is fed real fragments of the mail log: the same link appears in
 * the plain text and in the HTML part, and the HTML one carries entities.
 */
class MailLogReaderTest extends TestCase
{
    private function reader(): MailLogReader
    {
        return new MailLogReader(storage_path('logs/laravel.log'));
    }

    #[Test]
    public function it_reads_the_newest_link_first(): void
    {
        $log = <<<'LOG'
        [2026-09-20 10:00:00] local.DEBUG: Message-ID: <first@noircat>
        http://localhost:8000/email/verify/1/aaaa?expires=1790000001&signature=first
        [2026-09-20 10:05:00] local.DEBUG: Message-ID: <second@noircat>
        http://localhost:8000/email/verify/2/bbbb?expires=1790000002&signature=second
        LOG;

        $links = $this->reader()->parse($log, 2);

        $this->assertCount(2, $links);
        $this->assertStringContainsString('signature=second', $links[0]);
        $this->assertStringContainsString('signature=first', $links[1]);
    }

    #[Test]
    public function it_reports_a_link_once_even_when_the_html_part_repeats_it(): void
    {
        $log = <<<'LOG'
        <a href=3D"http://localhost:8000/email/verify/3/cccc?expires=1790000003&amp;signature=dup">Verify</a>
        Click here: http://localhost:8000/email/verify/3/cccc?expires=1790000003&signature=dup
        LOG;

        $links = $this->reader()->parse($log, 5);

        $this->assertCount(1, $links);
        // The entity is decoded so the printed link is clickable as-is.
        $this->assertStringContainsString('&signature=dup', $links[0]);
        $this->assertStringNotContainsString('&amp;', $links[0]);
    }

    #[Test]
    public function it_keeps_literal_equals_signs_intact(): void
    {
        // "expires=1789..." looks like the quoted-printable escape "=17"; a
        // blanket decode would silently corrupt the signature.
        $log = 'http://localhost:8000/email/verify/4/dddd?expires=1789999999&signature=keepme';

        $links = $this->reader()->parse($log);

        $this->assertSame(['http://localhost:8000/email/verify/4/dddd?expires=1789999999&signature=keepme'], $links);
    }

    #[Test]
    public function it_handles_a_quoted_printable_body(): void
    {
        $log = "http://localhost:8000/password/reset/eeee?expires=3D1790000005&signature=3Dqp\r\n";

        $links = $this->reader()->parse($log);

        $this->assertCount(1, $links);
        $this->assertStringContainsString('expires=1790000005', $links[0]);
        $this->assertStringContainsString('signature=qp', $links[0]);
    }

    #[Test]
    public function it_ignores_wrapped_punctuation_and_unrelated_urls(): void
    {
        $log = <<<'LOG'
        See (http://localhost:8000/email/verify/5/ffff?expires=1790000006&signature=wrapped).
        Docs live at https://noircat.example/docs
        LOG;

        $links = $this->reader()->parse($log);

        $this->assertCount(1, $links);
        $this->assertSame('http://localhost:8000/email/verify/5/ffff?expires=1790000006&signature=wrapped', $links[0]);
    }

    #[Test]
    public function it_returns_nothing_for_an_unrelated_log(): void
    {
        $this->assertSame([], $this->reader()->parse('nothing to see here'));
    }
}
