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
        // VULN: drafts are readable by anyone who guesses the id.
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        // VULN: no ownership and no permission check - any account can edit any
        // post, including drafts it does not own.
        return true;
    }

    public function delete(User $user, Post $post): bool
    {
        // VULN: any account can delete any post.
        return true;
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
        // VULN: comments are accepted on drafts too, so an account can attach
        // content to a post it is not supposed to know exists.
        return true;
    }

    public function report(User $user, Post $post): bool
    {
        return $user->can(Permission::REPORT_CREATE->value);
    }
}
