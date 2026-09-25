<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the four project roles and grants their permissions.
 *
 * Keep this matrix in sync with the policies and route middleware that check
 * the permissions.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var array<string, list<Permission>>
     */
    private const MATRIX = [
        UserRole::USER->value => [
            Permission::POST_CREATE,
            Permission::POST_UPDATE_OWN,
            Permission::POST_DELETE_OWN,
            Permission::COMMENT_CREATE,
            Permission::REPORT_CREATE,
            Permission::BOOK_UPLOAD,
            Permission::BOOK_DOWNLOAD,
            Permission::GUILD_CREATE,
        ],
        UserRole::GUILD_ADMIN->value => [
            Permission::GUILD_PLUGIN_INSTALL,
        ],
        UserRole::MODERATOR->value => [
            Permission::POST_DELETE_ANY,
            Permission::POST_PIN,
            Permission::POST_FEATURE,
            Permission::POST_LOCK,
            Permission::POST_MOVE,
            Permission::COMMENT_DELETE_ANY,
            Permission::REPORT_HANDLE,
            Permission::BOOK_DELETE_ANY,
        ],
        // admin receives every permission below.
        UserRole::ADMIN->value => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value);
        }

        foreach (self::MATRIX as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName);

            if ($roleName === UserRole::ADMIN->value) {
                $role->syncPermissions(PermissionModel::all());

                continue;
            }

            $role->syncPermissions(array_map(
                static fn (Permission $permission): string => $permission->value,
                $permissions,
            ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
