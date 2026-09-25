<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moderation state for posts.
 *
 * "featured" marks a thread worth surfacing above the ordinary list, and
 * "locked" closes a thread to new comments while keeping it readable. Both are
 * moderator decisions, so they are recorded in audit_logs as well.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_pinned');
            $table->boolean('is_locked')->default(false)->after('is_featured');

            // The list sorts pinned first, then featured, then newest.
            $table->index(['status', 'is_featured', 'created_at'], 'posts_featured_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_featured_index');
            $table->dropColumn(['is_featured', 'is_locked']);
        });
    }
};
