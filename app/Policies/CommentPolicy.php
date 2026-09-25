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

    /**
     * Putting a hidden comment back is the same authority as hiding it.
     */
    public function unhide(User $user, Comment $comment): bool
    {
        return $this->hide($user, $comment);
    }

    /**
     * Recovering a deleted comment follows the same authority as deleting it:
     * your own, or anyone's with comment:delete_any.
     */
    public function restore(User $user, Comment $comment): bool
    {
        return $this->delete($user, $comment);
    }

    public function report(User $user, Comment $comment): bool
    {
        return $user->can(Permission::REPORT_CREATE->value);
    }
}
