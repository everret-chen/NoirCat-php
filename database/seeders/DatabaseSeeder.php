<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds the security baseline: roles and permissions only.
     *
     * No user account is created on purpose - shipping default credentials is a
     * footgun in a security-focused project. Register through the API instead.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
