<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align the users table with PROJECT_SPEC_PHP section 3:
 * username / email / password / avatar / role.
 *
 * The historical create_users_table migration is never edited, so this
 * migration adds the missing columns and removes Laravel's default "name".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->unique()->after('id');
            $table->string('avatar')->nullable()->after('password');
            $table->string('role', 20)->default('user')->after('avatar');
        });

        // Backfill before tightening the constraint, so existing installs survive.
        DB::table('users')
            ->whereNull('username')
            ->orderBy('id')
            ->get(['id', 'email'])
            ->each(function (object $user): void {
                $base = strstr((string) $user->email, '@', true) ?: 'user'.$user->id;

                DB::table('users')->where('id', $user->id)->update([
                    'username' => mb_substr(preg_replace('/[^A-Za-z0-9_-]/', '', $base) ?: 'user'.$user->id, 0, 32),
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable(false)->change();
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        DB::table('users')->whereNull('name')->update(['name' => DB::raw('username')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'avatar', 'role']);
        });
    }
};
