<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\ReportContentRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;

/**
 * Reporting content from the pages.
 *
 * The two routes carry the target in the path, which is why they are separate
 * instead of taking a polymorphic payload from a form.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function storeForPost(ReportContentRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('report', $post);

        return $this->store($request, $post);
    }

    public function storeForComment(ReportContentRequest $request, Post $post, Comment $comment): RedirectResponse
    {
        $this->authorize('report', $comment);

        return $this->store($request, $comment);
    }

    private function store(ReportContentRequest $request, Post|Comment $reportable): RedirectResponse
    {
        $reporter = $request->user();
        \assert($reporter instanceof User);

        // Duplicate and self-report raise a BusinessException; the exception
        // handler turns that into a redirect back with the reason for a page
        // request, so there is nothing to catch here.
        $this->reports->create(
            $reporter,
            $reportable,
            $request->reason(),
            $request->validated('detail'),
        );

        return back()->with('status', __('forum.messages.report_created'));
    }
}
