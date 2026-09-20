<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('content'); // plain text: never rendered as HTML
            $table->string('status', 20)->default('visible'); // visible/hidden
            $table->unsignedInteger('like_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['post_id', 'status', 'created_at']);
            $table->index(['author_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
