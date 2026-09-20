<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Forum comments.
 *
 * Comment bodies are plain text, so they are escaped on output instead of being
 * rendered: there is no Markdown or HTML path for a comment to travel through.
 */
class CommentService
{
    /**
     * Maximum nesting depth (top level comment = 1).
     */
    public const MAX_DEPTH = 3;

    public function __construct(
        private readonly AuditLogService $auditLogs,
        private readonly PostService $posts,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $author, Post $post, array $attributes): Comment
    {
        $parentId = $attributes['parent_id'] ?? null;

        if ($parentId !== null) {
            $parent = Comment::query()->whereKey($parentId)->first();

            // A parent must exist, belong to the same post and stay within the
            // depth limit; otherwise a request could attach a reply anywhere.
            if ($parent === null || $parent->post_id !== $post->id) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, __('validation.exists', ['attribute' => 'parent_id']), 422);
            }

            if ($this->depthOf($parent) >= self::MAX_DEPTH) {
                throw new BusinessException(ErrorCode::BUSINESS_RULE_VIOLATION, __('forum.errors.max_depth'), 422);
            }
        }

        $comment = DB::transaction(function () use ($author, $post, $attributes, $parentId): Comment {
            $comment = new Comment([
                'parent_id' => $parentId,
                'content' => trim((string) $attributes['content']),
            ]);

            $comment->post_id = $post->id;
            $comment->author_id = $author->id;
            $comment->save();

            // Counters are maintained server side only.
            $post->increment('comment_count');
            $this->posts->touchActivity($post, $comment->created_at ?? now());

            return $comment;
        });

        $this->auditLogs->record(
            'forum.comment.created',
            ['post_id' => $post->id, 'parent_id' => $parentId],
            AuditLog::RESULT_SUCCESS,
            $comment,
            $author->id,
        );

        return $comment;
    }

    public function hide(User $moderator, Comment $comment): Comment
    {
        $comment->status = Comment::STATUS_HIDDEN;
        $comment->save();

        $this->auditLogs->record(
            'forum.comment.hidden',
            ['post_id' => $comment->post_id],
            AuditLog::RESULT_SUCCESS,
            $comment,
            $moderator->id,
        );

        return $comment;
    }

    public function delete(User $actor, Comment $comment): void
    {
        $comment->delete();

        $this->auditLogs->record(
            'forum.comment.deleted',
            ['post_id' => $comment->post_id],
            AuditLog::RESULT_SUCCESS,
            $comment,
            $actor->id,
        );
    }

    private function depthOf(Comment $comment): int
    {
        $depth = 1;
        $cursor = $comment;

        while ($cursor->parent_id !== null && $depth < self::MAX_DEPTH + 1) {
            $parent = $cursor->parent;
            if ($parent === null) {
                break;
            }

            $cursor = $parent;
            $depth++;
        }

        return $depth;
    }
}
