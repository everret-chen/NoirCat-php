<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\HandleReportRequest;
use App\Http\Requests\Forum\MovePostRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\CommentService;
use App\Services\ModerationService;
use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The governance pages: the report queue, the trash, and the moderator actions
 * that are reachable from a thread.
 *
 * The API exposes the same operations; both entry points go through the same
 * services and the same policies.
 */
class ModerationController extends Controller
{
    public function __construct(
        private readonly ModerationService $moderation,
        private readonly ReportService $reports,
        private readonly CommentService $comments,
    ) {
    }

    public function reports(Request $request): View
    {
        $this->authorize('viewAny', Report::class);

        $filters = $request->validate([
            'status' => ['sometimes', 'string', Rule::in([...ReportStatus::values(), 'all'])],
            'reason' => ['sometimes', 'string', Rule::in(\App\Enums\ReportReason::values())],
        ]);

        return view('moderation.reports', [
            'reports' => $this->reports->queue($filters, 20)->withQueryString(),
            'status' => (string) ($filters['status'] ?? ReportStatus::PENDING->value),
            'reason' => (string) ($filters['reason'] ?? ''),
            'pendingCount' => $this->reports->pendingCount(),
        ]);
    }

    public function resolveReport(HandleReportRequest $request, Report $report): RedirectResponse
    {
        $this->authorize('handle', $report);

        $this->reports->resolve($this->actor($request), $report, $request->validated('note'));

        return back()->with('status', __('forum.messages.report_resolved'));
    }

    public function dismissReport(HandleReportRequest $request, Report $report): RedirectResponse
    {
        $this->authorize('handle', $report);

        $this->reports->dismiss($this->actor($request), $report, $request->validated('note'));

        return back()->with('status', __('forum.messages.report_dismissed'));
    }

    public function trash(Request $request): View
    {
        $actor = $this->actor($request);

        return view('moderation.trash', [
            'posts' => $this->moderation->trashPosts($actor, 15)->withQueryString(),
            'comments' => $this->moderation->trashComments($actor, 15)->withQueryString(),
            'canSeeEverything' => $actor->can('post:delete_any'),
        ]);
    }

    public function pin(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('pin', $post);

        $post = $this->moderation->setPinned($this->actor($request), $post, ! $post->is_pinned);

        return back()->with('status', __(
            $post->is_pinned ? 'forum.messages.post_pinned' : 'forum.messages.post_unpinned'
        ));
    }

    public function feature(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('feature', $post);

        $post = $this->moderation->setFeatured($this->actor($request), $post, ! $post->is_featured);

        return back()->with('status', __(
            $post->is_featured ? 'forum.messages.post_featured' : 'forum.messages.post_unfeatured'
        ));
    }

    public function lock(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('lock', $post);

        $post = $this->moderation->setLocked($this->actor($request), $post, ! $post->is_locked);

        return back()->with('status', __(
            $post->is_locked ? 'forum.messages.post_locked' : 'forum.messages.post_unlocked'
        ));
    }

    public function move(MovePostRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('move', $post);

        $this->moderation->moveToCategory($this->actor($request), $post, $request->category());

        return back()->with('status', __('forum.messages.post_moved'));
    }

    public function restorePost(Request $request, int $post): RedirectResponse
    {
        $trashed = Post::onlyTrashed()->findOrFail($post);
        $this->authorize('restore', $trashed);

        $this->moderation->restorePost($this->actor($request), $trashed);

        return back()->with('status', __('forum.messages.post_restored'));
    }

    public function hideComment(Request $request, Comment $comment): RedirectResponse
    {
        $this->authorize('hide', $comment);

        $this->comments->hide($this->actor($request), $comment);

        return back()->with('status', __('forum.messages.comment_hidden'));
    }

    public function unhideComment(Request $request, Comment $comment): RedirectResponse
    {
        $this->authorize('unhide', $comment);

        $this->comments->unhide($this->actor($request), $comment);

        return back()->with('status', __('forum.messages.comment_unhidden'));
    }

    public function deleteComment(Request $request, Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);

        $this->comments->delete($this->actor($request), $comment);

        return back()->with('status', __('forum.messages.comment_deleted'));
    }

    public function restoreComment(Request $request, int $comment): RedirectResponse
    {
        $trashed = Comment::onlyTrashed()->findOrFail($comment);
        $this->authorize('restore', $trashed);

        $this->comments->restore($this->actor($request), $trashed);

        return back()->with('status', __('forum.messages.comment_restored'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        \assert($user instanceof User);

        return $user;
    }
}
