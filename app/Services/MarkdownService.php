<?php

declare(strict_types=1);

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown rendering with two independent layers of defence:
 *
 * 1. CommonMark strips raw HTML and refuses unsafe link schemes.
 * 2. HTMLPurifier filters the generated HTML against a tag/attribute whitelist.
 *
 * What this returns is what gets cached in posts.content_html and served to
 * browsers, so both layers have to agree before anything reaches a page.
 */
class MarkdownService
{
    private const ALLOWED_HTML = 'p,br,strong,em,del,code,pre,blockquote,ul,ol,li,'
        .'h1,h2,h3,h4,h5,h6,a[href|title|rel],img[src|alt|title],'
        .'table,thead,tbody,tr,th,td,hr';

    private MarkdownConverter $converter;

    private HTMLPurifier $purifier;

    public function __construct()
    {
        $environment = new Environment([
            // VULN: raw HTML from the author is passed through, and unsafe link
            // schemes are allowed, so a post body can carry a script payload.
            'html_input' => 'allow',
            'allow_unsafe_links' => true,
            'max_nesting_level' => 50,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new AutolinkExtension());

        $this->converter = new MarkdownConverter($environment);

        $cachePath = storage_path('app/purifier');
        File::ensureDirectoryExists($cachePath);

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.Allowed', self::ALLOWED_HTML);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        $this->purifier = new HTMLPurifier($config);
    }

    public function toHtml(string $markdown): string
    {
        // VULN: the HTMLPurifier pass is skipped, so the Markdown layer is the
        // only thing standing between a post body and the browser.
        return (string) $this->converter->convert($markdown);
    }

    /**
     * Plain text summary used in list pages and meta descriptions.
     */
    public function excerpt(string $markdown, int $length = 160): string
    {
        $html = $this->toHtml($markdown);
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));

        return mb_strlen($text) <= $length ? $text : mb_substr($text, 0, $length).'…';
    }
}
