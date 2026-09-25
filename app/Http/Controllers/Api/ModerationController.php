<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\MovePostRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\ReportResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\CommentService;
use App\Services\ModerationService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Moderator endpoints: the forum's governance surface.
 *
 * Every action is authorised through a policy first, so the permission matrix
 * lives in one place instead of being re-implemented per endpoint.
 */
class ModerationController extends Controller
{
    public function __construct(
        private readonly ModerationService $moderation,
        private readonly CommentService $comments,
        private readonly ReportService $reports,
    ) {
    }

    /**
     * POST /api/posts/{post}/feature
     */
    public function feature(Request $request, Post $post): JsonResponse
    {
        $this->authorize('feature', $post);

        $featured = $request->boolean('featured', true);
        $post = $this->moderation->setFeatured($this->actor($request), $post, $featured);

        return ApiResponse::success(
            new PostResource($post),
            __($featured ? 'forum.messages.post_featured' : 'forum.messages.post_unfeatured'),
        );
    }

    /**
     * POST /api/posts/{post}/lock
     */
    public function lock(Request $request, Post $post): JsonResponse
    {
        $this->authorize('lock', $post);

        $locked = $request->boolean('locked', true);
        $post = $this->moderation->setLocked($this->actor($request), $post, $locked);

        return ApiResponse::success(
            new PostResource($post),
            __($locked ? 'forum.messages.post_locked' : 'forum.messages.post_unlocked'),
        );
    }

    /**
     * PUT /api/posts/{post}/category
     */
    public function move(MovePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('move', $post);

        $post = $this->moderation->moveToCategory($this->actor($request), $post, $request->category());
        $post->load(['author:id,username,avatar', 'category']);

        return ApiResponse::success(new PostResource($post), __('forum.messages.post_moved'));
    }

    /**
     * POST /api/posts/{post}/restore
     */
    public function restorePost(Request $request, int $post): JsonResponse
    {
        $trashed = Post::onlyTrashed()->findOrFail($post);
        $this->authorize('restore', $trashed);

        $restored = $this->moderation->restorePost($this->actor($request), $trashed);
        $restored->load(['author:id,username,avatar', 'category']);

        return ApiResponse::success(new PostResource($restored), __('forum.messages.post_restored'));
    }

    /**
     * POST /api/comments/{comment}/unhide
     */
    public function unhideComment(Request $request, int $comment): JsonResponse
    {
        $hidden = Comment::query()->findOrFail($comment);
        $this->authorize('unhide', $hidden);

        $restored = $this->comments->unhide($this->actor($request), $hidden);

        return ApiResponse::success(new CommentResource($restored), __('forum.messages.comment_unhidden'));
    }

    /**
     * POST /api/comments/{comment}/restore
     */
    public function restoreComment(Request $request, int $comment): JsonResponse
    {
        $trashed = Comment::onlyTrashed()->findOrFail($comment);
        $this->authorize('restore', $trashed);

        $restored = $this->comments->restore($this->actor($request), $trashed);

        return ApiResponse::success(new CommentResource($restored), __('forum.messages.comment_restored'));
    }

    /**
     * GET /api/moderation/trash
     *
     * Moderators see everything that was deleted; members only see their own.
     */
    public function trash(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        return ApiResponse::success([
            'posts' => PostResource::collection($this->moderation->trashPosts($actor)->items()),
            'comments' => CommentResource::collection($this->moderation->trashComments($actor)->items()),
        ]);
    }

    /**
     * GET /api/reports
     *
     * The moderation queue. Defaults to open reports, oldest first.
     */
    public function reports(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);

        $filters = $request->validate([
            'status' => ['sometimes', 'string', Rule::in([...\App\Enums\ReportStatus::values(), 'all'])],
            'reason' => ['sometimes', 'string', Rule::in(\App\Enums\ReportReason::values())],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($filters['limit'] ?? 20);
        $paginator = $this->reports->queue($filters, $limit);

        return ApiResponse::paginated(
            ReportResource::collection($paginator->items()),
            $paginator->total(),
            $paginator->currentPage(),
            $limit,
        );
    }

    /**
     * POST /api/reports/{report}/resolve
     */
    public function resolveReport(\App\Http\Requests\Forum\HandleReportRequest $request, Report $report): JsonResponse
    {
        $this->authorize('handle', $report);

        $resolved = $this->reports->resolve($this->actor($request), $report, $request->validated('note'));

        return ApiResponse::success(new ReportResource($resolved), __('forum.messages.report_resolved'));
    }

    /**
     * POST /api/reports/{report}/dismiss
     */
    public function dismissReport(\App\Http\Requests\Forum\HandleReportRequest $request, Report $report): JsonResponse
    {
        $this->authorize('handle', $report);

        $dismissed = $this->reports->dismiss($this->actor($request), $report, $request->validated('note'));

        return ApiResponse::success(new ReportResource($dismissed), __('forum.messages.report_dismissed'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        \assert($user instanceof User);

        return $user;
    }
}
