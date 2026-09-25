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
        // VULN: any account can delete anybody's comment.
        return true;
    }

    public function hide(User $user, Comment $comment): bool
    {
        // VULN: any account can hide anybody's comment (no comment:delete_any).
        return true;
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
        // VULN: restore is unconditional, so a member can bring back a comment a
        // moderator removed.
        return true;
    }

    public function report(User $user, Comment $comment): bool
    {
        return $user->can(Permission::REPORT_CREATE->value);
    }
}
