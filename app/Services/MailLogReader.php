<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Reads the actionable links back out of the mail log.
 *
 * With MAIL_MAILER=log every message lands in storage/logs/laravel.log instead
 * of an inbox, which is fine for development but awkward when the verification
 * link is the whole point. This keeps the parsing out of the console command so
 * it can be tested against real log fragments.
 */
class MailLogReader
{
    /**
     * Brackets and parentheses are excluded so a link quoted inside prose or
     * inside an HTML body is not swallowed with its wrapper.
     */
    private const LINK_PATTERN = '#https?://[^\s"\'<>()\[\]]+/(?:email/verify|password/reset)/[^\s"\'<>()\[\]]+#';

    /**
     * Only the tail of the file is scanned: the newest mail is the one that
     * matters, and the log grows without bound.
     */
    private const TAIL_BYTES = 524288;

    public function __construct(private readonly string $path)
    {
    }

    public static function default(): self
    {
        return new self(storage_path('logs/laravel.log'));
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Newest first, de-duplicated: the same link appears in the text part and
     * again in the HTML part of the message.
     *
     * @return list<string>
     */
    public function newest(int $limit = 3): array
    {
        if (! File::exists($this->path)) {
            return [];
        }

        $contents = (string) File::get($this->path);

        if (strlen($contents) > self::TAIL_BYTES) {
            $contents = substr($contents, -self::TAIL_BYTES);
        }

        return $this->parse($contents, $limit);
    }

    /**
     * @return list<string>
     */
    public function parse(string $contents, int $limit = 3): array
    {
        // The log transport stores the message verbatim, so the links are plain
        // text here: a literal "expires=1789..." must survive untouched, which
        // is why the buffer is never decoded wholesale ("=17" is a valid
        // quoted-printable escape and would corrupt the signature).
        $body = $contents;

        // A quoted-printable body is recognisable by its escaped equals signs.
        // Only then are the soft line breaks undone and "=3D" restored.
        if (str_contains($contents, '=3D')) {
            $body = str_replace('=3D', '=', str_replace(["=\r\n", "=\n"], '', $contents));
        }

        $links = $this->match($body);

        if ($links === [] && $body !== $contents) {
            $links = $this->match($contents);
        }

        $unique = [];

        foreach (array_reverse($links) as $link) {
            $unique[$link] = true;

            if (count($unique) >= max(1, $limit)) {
                break;
            }
        }

        return array_keys($unique);
    }

    /**
     * The log file may disappear between the exists() check and the read.
     *
     * @return list<string>
     */
    public function newestOrEmpty(int $limit = 3): array
    {
        try {
            return $this->newest($limit);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function match(string $contents): array
    {
        preg_match_all(self::LINK_PATTERN, $contents, $matches);

        $links = [];

        foreach ($matches[0] as $link) {
            $links[] = html_entity_decode(rtrim($link, ".,;:'\""));
        }

        return $links;
    }
}
