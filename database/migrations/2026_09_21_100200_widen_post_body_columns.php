<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the post body columns for MySQL.
 *
 * MySQL's TEXT holds 65,535 *bytes*, while the validation allows 50,000
 * characters: a long Chinese post is roughly three bytes per character, so a
 * submission accepted on SQLite would fail on MySQL with "Data too long".
 * MEDIUMTEXT (16 MB) removes the ceiling without changing the validation rules.
 *
 * The historical migration is left untouched on purpose (AGENTS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->mediumText('content')->change();
            $table->mediumText('content_html')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('content')->change();
            $table->text('content_html')->nullable()->change();
        });
    }
};
