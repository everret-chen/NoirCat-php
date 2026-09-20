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

    public function like(User $user, Post $post): bool
    {
        return $post->isPublished() || $user->id === $post->author_id;
    }

    /**
     * Only published posts accept comments.
     *
     * Without this rule any authenticated account could attach a comment to a
     * draft it is not allowed to read, and could pollute that draft's counter.
     */
    public function comment(User $user, Post $post): bool
    {
        return $post->isPublished();
    }
}
