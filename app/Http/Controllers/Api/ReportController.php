<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Filing a report.
 *
 * Kept apart from the moderation controller: reporting is something every
 * member does, handling reports is not.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    /**
     * POST /api/reports
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $reporter = $request->user();
        \assert($reporter instanceof User);

        // Route model binding does not apply here: the target is polymorphic, so
        // the type is resolved from the validated payload and scoped to the
        // models a member may report.
        $reportable = $request->reportableType()::query()->findOrFail($request->integer('reportable_id'));

        $report = $this->reports->create(
            $reporter,
            $reportable,
            $request->reason(),
            $request->validated('detail'),
        );

        return ApiResponse::success(new ReportResource($report), __('forum.messages.report_created'), 201);
    }
}
