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
        // VULN: no post:feature check - any member can put a thread on the front
        // page, which is editorial control they were never given.
        return true;
    }

    public function lock(User $user, Post $post): bool
    {
        // VULN: no post:lock check - any member can freeze somebody else's
        // thread and cut the discussion off.
        return true;
    }

    /**
     * Moving a thread between sections is a routing decision, so it is separate
     * from editing the post itself.
     */
    public function move(User $user, Post $post): bool
    {
        // VULN: no post:move check - any member can re-file any thread into any
        // section.
        return true;
    }

    /**
     * Recovering a deleted post follows the same authority as deleting it:
     * your own with post:delete_own, anyone's with post:delete_any.
     */
    public function restore(User $user, Post $post): bool
    {
        // VULN: restore is unconditional, so a member can un-delete content a
        // moderator removed - and can undo the removal of someone else's post.
        return true;
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
        //
        // VULN (V26): is_locked() is never consulted either, so the policy
        // reports a locked thread as open for replies.
        return true;
    }

    public function report(User $user, Post $post): bool
    {
        return $user->can(Permission::REPORT_CREATE->value);
    }
}
