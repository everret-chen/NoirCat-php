<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Exceptions\BusinessException;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * The report queue.
 *
 * A report is a member's claim, not a verdict: filing one changes nothing about
 * the reported content. Moderators then act on the content (hide, delete, edit)
 * and close the report, so the two decisions stay separate in the audit trail.
 */
class ReportService
{
    /**
     * Content types a member is allowed to report.
     */
    private const REPORTABLE = [Post::class, Comment::class];

    public function __construct(private readonly AuditLogService $auditLogs)
    {
    }

    public function create(User $reporter, Model $reportable, ReportReason $reason, ?string $detail = null): Report
    {
        if (! in_array($reportable::class, self::REPORTABLE, true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, __('forum.errors.report_unsupported'), 422);
        }

        // Reporting deleted content is pointless: there is nothing left to act
        // on, and letting it through would leave unresolvable queue entries.
        if (method_exists($reportable, 'trashed') && $reportable->trashed()) {
            throw new BusinessException(ErrorCode::RESOURCE_NOT_FOUND, __('forum.errors.report_gone'), 404);
        }

        if ($this->authorIdOf($reportable) === $reporter->id) {
            throw new BusinessException(ErrorCode::BUSINESS_RULE_VIOLATION, __('forum.errors.report_own'), 422);
        }

        if ($this->alreadyReported($reporter, $reportable)) {
            throw new BusinessException(ErrorCode::BUSINESS_RULE_VIOLATION, __('forum.errors.report_duplicate'), 409);
        }

        $report = new Report([
            'reason' => $reason,
            'detail' => $detail === null ? null : trim($detail),
        ]);

        // Identity and target come from the session and the route binding.
        $report->reporter_id = $reporter->id;
        $report->reportable_type = $reportable->getMorphClass();
        $report->reportable_id = $reportable->getKey();
        $report->status = ReportStatus::PENDING;
        $report->save();

        $this->auditLogs->record(
            'moderation.report.created',
            [
                'reason' => $reason->value,
                'reportable_type' => $report->reportable_type,
                'reportable_id' => $report->reportable_id,
            ],
            AuditLog::RESULT_SUCCESS,
            $report,
            $reporter->id,
        );

        return $report;
    }

    /**
     * The claim was valid and the content has been dealt with.
     */
    public function resolve(User $moderator, Report $report, ?string $note = null): Report
    {
        return $this->close($moderator, $report, ReportStatus::RESOLVED, $note);
    }

    /**
     * The claim was reviewed and no action is needed.
     */
    public function dismiss(User $moderator, Report $report, ?string $note = null): Report
    {
        return $this->close($moderator, $report, ReportStatus::DISMISSED, $note);
    }

    /**
     * Open reports first, oldest first so nothing is left behind.
     *
     * @param  array{status?: string|null, reason?: string|null}  $filters
     * @return LengthAwarePaginator<int, Report>
     */
    public function queue(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $status = $filters['status'] ?? ReportStatus::PENDING->value;

        return Report::query()
            ->when(
                $status === 'all',
                fn ($query) => $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END"),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                ($filters['reason'] ?? '') !== '',
                fn ($query) => $query->where('reason', $filters['reason']),
            )
            ->with([
                'reporter:id,username,avatar',
                'handler:id,username',
                'reportable' => fn ($morphTo) => $morphTo->morphWith([
                    Post::class => ['author:id,username', 'category:id,name,name_en,slug'],
                    Comment::class => ['author:id,username', 'post:id,title'],
                ]),
            ])
            ->orderBy('created_at')->orderBy('id')
            ->paginate($perPage, ['*'], 'reports_page');
    }

    public function pendingCount(): int
    {
        return Report::query()->open()->count();
    }

    /**
     * Users who can see the queue also see how many reports a piece of content
     * has collected, which is what turns a single complaint into a pattern.
     */
    public function openCountFor(Model $reportable): int
    {
        return Report::query()
            ->open()
            ->where('reportable_type', $reportable->getMorphClass())
            ->where('reportable_id', $reportable->getKey())
            ->count();
    }

    private function close(User $moderator, Report $report, ReportStatus $status, ?string $note): Report
    {
        if (! $report->isOpen()) {
            // Two moderators clicking at the same time must not overwrite who
            // decided what.
            throw new BusinessException(ErrorCode::BUSINESS_RULE_VIOLATION, __('forum.errors.report_already_handled'), 409);
        }

        $report->status = $status;
        $report->handled_by = $moderator->id;
        $report->handled_at = now();
        $report->resolution_note = $note === null ? null : trim($note);
        $report->save();

        $this->auditLogs->record(
            $status === ReportStatus::RESOLVED ? 'moderation.report.resolved' : 'moderation.report.dismissed',
            [
                'reason' => $report->reason->value,
                'reportable_type' => $report->reportable_type,
                'reportable_id' => $report->reportable_id,
                'note' => $report->resolution_note,
            ],
            AuditLog::RESULT_SUCCESS,
            $report,
            $moderator->id,
        );

        return $report;
    }

    private function alreadyReported(User $reporter, Model $reportable): bool
    {
        return Report::query()
            ->where('reporter_id', $reporter->id)
            ->where('reportable_type', $reportable->getMorphClass())
            ->where('reportable_id', $reportable->getKey())
            ->exists();
    }

    private function authorIdOf(Model $reportable): ?int
    {
        if ($reportable instanceof Post || $reportable instanceof Comment) {
            return (int) $reportable->author_id;
        }

        return null;
    }
}
