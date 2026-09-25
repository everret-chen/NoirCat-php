<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VULN (V22): the reports_reporter_unique index is the storage level guarantee
 * that one account can report the same object once. Dropping it leaves the
 * service as the only place that could refuse a duplicate - and on this branch
 * that check is gone as well, so a single account can flood the moderation
 * queue with identical rows.
 *
 * Kept as a separate migration instead of editing the create_reports_table
 * migration, because historical migrations are frozen once they have shipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropUnique('reports_reporter_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->unique(['reporter_id', 'reportable_type', 'reportable_id'], 'reports_reporter_unique');
        });
    }
};
