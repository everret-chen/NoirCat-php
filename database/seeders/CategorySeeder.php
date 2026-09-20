<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Default forum sections and book categories.
 *
 * Slugs are stable identifiers used in URLs and API filters, so they are never
 * translated; only the display names differ per locale.
 */
class CategorySeeder extends Seeder
{
    /**
     * @var list<array{type: string, slug: string, name: string, name_en: string, sort_order: int}>
     */
    private const CATEGORIES = [
        ['type' => Category::TYPE_POST, 'slug' => 'announcements', 'name' => '站务公告', 'name_en' => 'Announcements', 'sort_order' => 1],
        ['type' => Category::TYPE_POST, 'slug' => 'general', 'name' => '综合讨论', 'name_en' => 'General', 'sort_order' => 2],
        ['type' => Category::TYPE_POST, 'slug' => 'security', 'name' => '安全技术', 'name_en' => 'Security', 'sort_order' => 3],
        ['type' => Category::TYPE_POST, 'slug' => 'tools', 'name' => '工具与资源', 'name_en' => 'Tools & Resources', 'sort_order' => 4],
        ['type' => Category::TYPE_POST, 'slug' => 'help', 'name' => '求助问答', 'name_en' => 'Help & Q&A', 'sort_order' => 5],

        ['type' => Category::TYPE_BOOK, 'slug' => 'network-security', 'name' => '网络安全', 'name_en' => 'Network Security', 'sort_order' => 1],
        ['type' => Category::TYPE_BOOK, 'slug' => 'web-security', 'name' => 'Web 安全', 'name_en' => 'Web Security', 'sort_order' => 2],
        ['type' => Category::TYPE_BOOK, 'slug' => 'programming', 'name' => '编程开发', 'name_en' => 'Programming', 'sort_order' => 3],
        ['type' => Category::TYPE_BOOK, 'slug' => 'ctf', 'name' => 'CTF 与靶场', 'name_en' => 'CTF & Labs', 'sort_order' => 4],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $category) {
            Category::query()->updateOrCreate(
                ['type' => $category['type'], 'slug' => $category['slug']],
                $category,
            );
        }
    }
}
