<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Moderator actions on forum content.
 *
 * Every method here changes the visibility or the reach of somebody else's
 * content, so each one is authorised by a policy before it is called and
 * audited afterwards. The service keeps the state change and its audit entry in
 * one place instead of scattering them across controllers.
 *
 * Author facing operations (create, update, delete your own post, likes) stay in
 * PostService and CommentService.
 */
class ModerationService
{
    public function __construct(private readonly AuditLogService $auditLogs)
    {
    }

    public function setPinned(User $actor, Post $post, bool $pinned): Post
    {
        return $this->flag($actor, $post, 'is_pinned', $pinned, 'pinned', 'unpinned');
    }

    public function setFeatured(User $actor, Post $post, bool $featured): Post
    {
        return $this->flag($actor, $post, 'is_featured', $featured, 'featured', 'unfeatured');
    }

    /**
     * A locked thread stays readable but stops accepting comments.
     */
    public function setLocked(User $actor, Post $post, bool $locked): Post
    {
        return $this->flag($actor, $post, 'is_locked', $locked, 'locked', 'unlocked');
    }

    public function moveToCategory(User $actor, Post $post, ?Category $category): Post
    {
        $from = $post->category_id;
        $post->category_id = $category?->id;
        $post->save();

        $this->auditLogs->record(
            'forum.post.moved',
            ['from_category_id' => $from, 'to_category_id' => $post->category_id, 'slug' => $category?->slug],
            AuditLog::RESULT_SUCCESS,
            $post,
            $actor->id,
        );

        return $post;
    }

    /**
     * Bring a soft deleted post back. Comments that were removed with it stay
     * removed: restoring one piece of content must not silently restore others.
     */
    public function restorePost(User $actor, Post $post): Post
    {
        $post->restore();

        $this->auditLogs->record(
            'forum.post.restored',
            ['title' => $post->title],
            AuditLog::RESULT_SUCCESS,
            $post,
            $actor->id,
        );

        return $post;
    }

    /**
     * Soft deleted posts, newest deletion first.
     *
     * Moderators see every deleted post; everyone else only sees their own, and
     * the filter is applied here rather than trusted from the request.
     *
     * @return LengthAwarePaginator<int, Post>
     */
    public function trashPosts(User $viewer, int $perPage = 15): LengthAwarePaginator
    {
        // VULN: the post:delete_any filter is gone, so every member sees every
        // deleted post - including content a moderator removed.
        return Post::onlyTrashed()
            ->with(['author:id,username,avatar', 'category:id,name,name_en,slug'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage, ['*'], 'posts_page');
    }

    /**
     * Soft deleted comments, newest deletion first. Hidden (but not deleted)
     * comments are a moderation state, not trash, and are not listed here.
     *
     * @return LengthAwarePaginator<int, Comment>
     */
    public function trashComments(User $viewer, int $perPage = 15): LengthAwarePaginator
    {
        // VULN: the comment:delete_any filter is gone, so every member sees
        // every deleted comment.
        return Comment::onlyTrashed()
            ->with(['author:id,username,avatar', 'post:id,title,slug'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage, ['*'], 'comments_page');
    }

    /**
     * @param  'is_pinned'|'is_featured'|'is_locked'  $column
     */
    private function flag(User $actor, Post $post, string $column, bool $value, string $on, string $off): Post
    {
        $post->{$column} = $value;
        $post->save();

        $this->auditLogs->record(
            'forum.post.'.($value ? $on : $off),
            ['title' => $post->title],
            AuditLog::RESULT_SUCCESS,
            $post,
            $actor->id,
        );

        return $post;
    }
}
