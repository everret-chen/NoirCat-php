<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Primary user roles.
 *
 * Values match both the users.role column and the role names registered in the
 * permission tables. Spatie's permission tables are the source of truth for
 * authorization decisions; users.role is a denormalized label kept in sync by
 * App\Services\UserService so it can be queried and displayed cheaply.
 */
enum UserRole: string
{
    case USER = 'user';
    case GUILD_ADMIN = 'guild_admin';
    case MODERATOR = 'moderator';
    case ADMIN = 'admin';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    public function label(): string
    {
        return __('roles.'.$this->value);
    }
}
