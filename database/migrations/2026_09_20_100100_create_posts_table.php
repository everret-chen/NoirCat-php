<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('content');                  // Markdown source of truth
            $table->text('content_html')->nullable(); // rendered and purified cache
            $table->string('status', 20)->default('published'); // draft/published
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->timestamp('last_commented_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // List page: pinned first, then newest, filtered by status.
            $table->index(['status', 'is_pinned', 'created_at']);
            $table->index(['category_id', 'status', 'created_at']);
            $table->index(['author_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
