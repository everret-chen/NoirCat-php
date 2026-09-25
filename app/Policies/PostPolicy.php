<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Published posts are public; drafts are visible to their author and to
     * anyone who can moderate.
     */
    public function view(?User $user, Post $post): bool
    {
        if ($post->isPublished()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $user->id === $post->author_id || $user->can(Permission::POST_DELETE_ANY->value);
    }

    public function update(User $user, Post $post): bool
    {
        if ($user->id === $post->author_id && $user->can(Permission::POST_UPDATE_OWN->value)) {
            return true;
        }

        // Moderators and admins may edit any post.
        return $user->can(Permission::POST_DELETE_ANY->value);
    }

    public function delete(User $user, Post $post): bool
    {
        if ($user->id === $post->author_id && $user->can(Permission::POST_DELETE_OWN->value)) {
            return true;
        }

        return $user->can(Permission::POST_DELETE_ANY->value);
    }

    public function pin(User $user, Post $post): bool
    {
        return $user->can(Permission::POST_PIN->value);
    }

    public function feature(User $user, Post $post): bool
    {
        return $user->can(Permission::POST_FEATURE->value);
    }

    public function lock(User $user, Post $post): bool
    {
        return $user->can(Permission::POST_LOCK->value);
    }

    /**
     * Moving a thread between sections is a routing decision, so it is separate
     * from editing the post itself.
     */
    public function move(User $user, Post $post): bool
    {
        return $user->can(Permission::POST_MOVE->value);
    }

    /**
     * Recovering a deleted post follows the same authority as deleting it:
     * your own with post:delete_own, anyone's with post:delete_any.
     */
    public function restore(User $user, Post $post): bool
    {
        return $this->delete($user, $post);
    }

    public function like(User $user, Post $post): bool
    {
        return $post->isPublished() || $user->id === $post->author_id;
    }

    /**
     * Only published posts accept comments, and a locked thread stops accepting
     * them entirely; the moderator action would mean nothing otherwise.
     */
    public function comment(User $user, Post $post): bool
    {
        return $post->isPublished() && ! $post->isLocked();
    }

    public function report(User $user, Post $post): bool
    {
        return $user->can(Permission::REPORT_CREATE->value);
    }
}
