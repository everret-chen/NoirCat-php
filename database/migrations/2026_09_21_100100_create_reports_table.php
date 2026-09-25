<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User reports about posts and comments.
 *
 * Reporting is polymorphic because every content type added later (book review,
 * guild message) needs the same queue. The unique index is what keeps the queue
 * honest: one report per account per object, so a single user cannot flood the
 * moderators by refreshing a button.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // Reports outlive the account that filed them: the resolution is
            // still evidence about the reported content.
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();

            $table->morphs('reportable');

            $table->string('reason', 32);
            $table->text('detail')->nullable();

            $table->string('status', 20)->default('pending'); // pending/resolved/dismissed
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('resolution_note', 500)->nullable();

            $table->timestamps();

            $table->unique(['reporter_id', 'reportable_type', 'reportable_id'], 'reports_reporter_unique');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
