<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function delete(User $user, Comment $comment): bool
    {
        if ($user->id === $comment->author_id) {
            return true;
        }

        return $user->can(Permission::COMMENT_DELETE_ANY->value);
    }

    public function hide(User $user, Comment $comment): bool
    {
        return $user->can(Permission::COMMENT_DELETE_ANY->value);
    }
}
