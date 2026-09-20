<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ReportReason;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\StoreCommentRequest;
use App\Http\Requests\Forum\StorePostRequest;
use App\Http\Requests\Forum\UpdatePostRequest;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\CommentService;
use App\Services\PostService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ForumController extends Controller
{
    public function __construct(
        private readonly PostService $posts,
        private readonly CommentService $comments,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'category' => $request->query('category'),
            'q' => $request->query('q'),
            'sort' => $request->query('sort', 'latest'),
        ];

        // "My posts" also surfaces the author's own drafts, which are hidden
        // from everyone else by PostPolicy.
        $viewer = $request->user();
        if ($request->boolean('mine') && $viewer !== null) {
            $filters['author_id'] = $viewer->getAuthIdentifier();
            $filters['include_unpublished'] = true;
        }

        return view('forum.index', [
            'posts' => $this->posts->paginate($filters, 15)->withQueryString(),
            'categories' => $this->postCategories(),
            'categorySlug' => (string) ($filters['category'] ?? ''),
            'sort' => (string) $filters['sort'],
            'search' => (string) ($filters['q'] ?? ''),
            'showMine' => $request->boolean('mine'),
        ]);
    }

    public function show(Request $request, Post $post): View
    {
        $this->authorize('view', $post);

        $this->posts->recordView($post, (string) $request->ip());
        $post->load(['author', 'category']);

        $visible = $post->comments()
            ->where('status', Comment::STATUS_VISIBLE)
            ->with('author')
            ->orderBy('created_at')->orderBy('id')
            ->get();

        /** @var Collection<int|string, Collection<int, Comment>> $byParent */
        $byParent = $visible->groupBy(fn (Comment $comment): int => $comment->parent_id ?? 0);

        $viewer = $request->user();

        return view('forum.show', [
            'post' => $post,
            'byParent' => $byParent,
            'topLevelComments' => $byParent->get(0, new Collection()),
            'maxDepth' => CommentService::MAX_DEPTH,
            'likedByMe' => $viewer !== null
                && $post->likes()->where('user_id', $viewer->getAuthIdentifier())->exists(),
            // Only rendered for moderators, but cheap enough to always pass.
            'moveTargets' => $this->postCategories(),
            'reportReasons' => ReportReason::cases(),
            'myOpenReports' => $viewer === null ? [] : $this->openReportsOf($viewer, $post),
        ]);
    }

    /**
     * Ids of the posts this viewer already reported on this page, so the button
     * can say "宸蹭妇鎶? instead of failing with a conflict.
     *
     * @return array<string, list<int>>
     */
    private function openReportsOf(User $viewer, Post $post): array
    {
        $reports = Report::query()
            ->where('reporter_id', $viewer->getAuthIdentifier())
            ->where(function ($query) use ($post): void {
                $query->where(fn ($inner) => $inner
                    ->where('reportable_type', $post->getMorphClass())
                    ->where('reportable_id', $post->getKey()))
                    ->orWhere(fn ($inner) => $inner
                        ->where('reportable_type', (new Comment())->getMorphClass())
                        ->whereIn('reportable_id', $post->comments()->select('id')));
            })
            ->get(['reportable_type', 'reportable_id']);

        $morph = $post->getMorphClass();
        $commentMorph = (new Comment())->getMorphClass();

        return [
            'post' => $reports->where('reportable_type', $morph)->pluck('reportable_id')->all(),
            'comments' => $reports->where('reportable_type', $commentMorph)->pluck('reportable_id')->all(),
        ];
    }

    public function create(): View
    {
        return view('forum.create', [
            'categories' => $this->postCategories(),
        ]);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $author = $request->user();
        \assert($author instanceof User);

        $post = $this->posts->create($author, $request->validated());

        return redirect()
            ->route('forum.show', $post)
            ->with('status', __('forum.messages.post_created'));
    }

    public function edit(Post $post): View
    {
        $this->authorize('update', $post);

        return view('forum.edit', [
            'post' => $post,
            'categories' => $this->postCategories(),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $editor = $request->user();
        \assert($editor instanceof User);

        $this->posts->update($editor, $post, $request->validated());

        return redirect()
            ->route('forum.show', $post)
            ->with('status', __('forum.messages.post_updated'));
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $actor = $request->user();
        \assert($actor instanceof User);

        $this->posts->delete($actor, $post);

        return redirect()
            ->route('forum.index')
            ->with('status', __('forum.messages.post_deleted'));
    }

    public function comment(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        // VULN: no comment check - drafts accept comments too.
        $author = $request->user();
        \assert($author instanceof User);

        try {
            $this->comments->create($author, $post, $request->validated());
        } catch (BusinessException $exception) {
            return back()->withInput()->withErrors(['content' => $exception->getMessage()]);
        }

        return back()->with('status', __('forum.messages.comment_created'));
    }

    public function like(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('like', $post);

        $user = $request->user();
        \assert($user instanceof User);

        $this->posts->like($user, $post);

        return back()->with('status', __('forum.messages.post_liked'));
    }

    public function unlike(Request $request, Post $post): RedirectResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        $this->posts->unlike($user, $post);

        return back()->with('status', __('forum.messages.post_unliked'));
    }

    /**
     * @return Collection<int, Category>
     */
    private function postCategories(): Collection
    {
        return Category::query()
            ->where('type', Category::TYPE_POST)
            ->orderBy('sort_order')
            ->get();
    }
}
