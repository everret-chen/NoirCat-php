<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comment;
use App\Models\Post;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(private readonly CommentService $comments)
    {
    }

    /**
     * GET /api/posts/{post}/comments
     *
     * Returns a flat, oldest first list; the client nests by parent_id.
     */
    public function index(Post $post): JsonResponse
    {
        // Drafts are visible to their author and to moderators only; the thread
        // must not be readable just because the id was guessed.
        $this->authorize('view', $post);

        $comments = $post->comments()
            ->where('status', Comment::STATUS_VISIBLE)
            ->with('author:id,username,avatar')
            ->orderBy('created_at')->orderBy('id')
            ->limit(500)
            ->get();

        return ApiResponse::success(CommentResource::collection($comments));
    }

    /**
     * POST /api/posts/{post}/comments
     */
    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $this->authorize('comment', $post);

        $author = $request->user();
        \assert($author instanceof \App\Models\User);

        $comment = $this->comments->create($author, $post, $request->validated());
        $comment->load('author:id,username,avatar');

        return ApiResponse::success(
            new CommentResource($comment),
            __('forum.messages.comment_created'),
            201,
        );
    }

    /**
     * DELETE /api/comments/{comment}
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $actor = $request->user();
        \assert($actor instanceof \App\Models\User);

        $this->comments->delete($actor, $comment);

        return ApiResponse::success(null, __('forum.messages.comment_deleted'));
    }

    /**
     * POST /api/comments/{comment}/hide
     */
    public function hide(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('hide', $comment);

        $moderator = $request->user();
        \assert($moderator instanceof \App\Models\User);

        return ApiResponse::success(
            new CommentResource($this->comments->hide($moderator, $comment)),
            __('forum.messages.comment_hidden'),
        );
    }
}
