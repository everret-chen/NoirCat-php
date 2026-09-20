<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Permission names granted through spatie/laravel-permission.
 *
 * Naming convention: resource:action, optionally scoped with :own / :any.
 * The role -> permission matrix lives in RolesAndPermissionsSeeder.
 */
enum Permission: string
{
    // Forum
    case POST_CREATE = 'post:create';
    case POST_UPDATE_OWN = 'post:update_own';
    case POST_DELETE_OWN = 'post:delete_own';
    case POST_DELETE_ANY = 'post:delete_any';
    case POST_PIN = 'post:pin';
    case COMMENT_CREATE = 'comment:create';
    case COMMENT_DELETE_ANY = 'comment:delete_any';

    // Books
    case BOOK_UPLOAD = 'book:upload';
    case BOOK_DOWNLOAD = 'book:download';
    case BOOK_DELETE_ANY = 'book:delete_any';
    case BOOK_APPROVE = 'book:approve';

    // Learning
    case LEARN_MANAGE = 'learn:manage';

    // Guilds and plugins
    case GUILD_CREATE = 'guild:create';
    case GUILD_MANAGE_ANY = 'guild:manage_any';
    case GUILD_PLUGIN_INSTALL = 'guild:plugin:install';

    // Users, moderation and security
    case REPORT_HANDLE = 'report:handle';
    case USER_MANAGE = 'user:manage';
    case AUDIT_VIEW = 'audit:view';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
