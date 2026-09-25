<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\StorePostRequest;
use App\Http\Requests\Forum\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Http\Responses\ApiResponse;
use App\Models\Post;
use App\Models\User;
use App\Services\ModerationService;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function __construct(
        private readonly PostService $posts,
        private readonly ModerationService $moderation,
    ) {
    }

    /**
     * GET /api/posts
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['sometimes', 'string', 'max:64'],
            'q' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['latest', 'active', 'popular', 'views'])],
            'author_id' => ['sometimes', 'integer'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($filters['limit'] ?? 20);
        $paginator = $this->posts->paginate($filters, $limit);

        return ApiResponse::paginated(
            PostResource::collection($paginator->items()),
            $paginator->total(),
            $paginator->currentPage(),
            $limit,
        );
    }

    /**
     * GET /api/posts/{post}
     */
    public function show(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $post->loadMissing(['author:id,username,avatar', 'category']);
        $this->posts->recordView($post, (string) $request->ip());

        return ApiResponse::success((new PostResource($post))->withBody());
    }

    /**
     * POST /api/posts
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $author = $request->user();
        \assert($author instanceof \App\Models\User);

        $post = $this->posts->create($author, $request->validated());
        $post->load(['author:id,username,avatar', 'category']);

        return ApiResponse::success(
            (new PostResource($post))->withBody(),
            __('forum.messages.post_created'),
            201,
        );
    }

    /**
     * PUT /api/posts/{post}
     */
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $editor = $request->user();
        \assert($editor instanceof \App\Models\User);

        $post = $this->posts->update($editor, $post, $request->validated());
        $post->load(['author:id,username,avatar', 'category']);

        return ApiResponse::success(
            (new PostResource($post))->withBody(),
            __('forum.messages.post_updated'),
        );
    }

    /**
     * DELETE /api/posts/{post}
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $actor = $request->user();
        \assert($actor instanceof \App\Models\User);

        $this->posts->delete($actor, $post);

        return ApiResponse::success(null, __('forum.messages.post_deleted'));
    }

    /**
     * POST /api/posts/{post}/like
     */
    public function like(Request $request, Post $post): JsonResponse
    {
        $this->authorize('like', $post);

        $user = $request->user();
        \assert($user instanceof \App\Models\User);

        return ApiResponse::success(new PostResource($this->posts->like($user, $post)), __('forum.messages.post_liked'));
    }

    /**
     * DELETE /api/posts/{post}/like
     */
    public function unlike(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();
        \assert($user instanceof \App\Models\User);

        return ApiResponse::success(new PostResource($this->posts->unlike($user, $post)), __('forum.messages.post_unliked'));
    }

    /**
     * POST /api/posts/{post}/pin
     */
    public function pin(Request $request, Post $post): JsonResponse
    {
        $this->authorize('pin', $post);

        $moderator = $request->user();
        \assert($moderator instanceof \App\Models\User);

        $pinned = $request->boolean('pinned', true);
        $post = $this->moderation->setPinned($moderator, $post, $pinned);

        return ApiResponse::success(
            new PostResource($post),
            __($pinned ? 'forum.messages.post_pinned' : 'forum.messages.post_unpinned'),
        );
    }
}
