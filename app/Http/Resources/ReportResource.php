<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A report in the moderation queue.
 *
 * "reportable" is null when the content was deleted after the report was filed;
 * the resource reports that explicitly instead of hiding the queue entry, so a
 * moderator can still close it.
 *
 * @mixin \App\Models\Report
 */
class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'detail' => $this->detail,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reporter' => $this->whenLoaded('reporter', fn () => new AuthorResource($this->reporter)),
            'handler' => $this->whenLoaded('handler', fn () => $this->handler === null ? null : new AuthorResource($this->handler)),
            'content_available' => $this->resource->reportable !== null,
            'reportable_type' => class_basename($this->reportable_type),
            'reportable_id' => $this->reportable_id,
            'reportable' => $this->reportableSummary(),
            'resolution_note' => $this->resolution_note,
            'handled_at' => $this->handled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * A short, safe summary of whatever was reported: the queue must stay
     * readable without exposing a full comment body through the API.
     *
     * @return array<string, mixed>|null
     */
    private function reportableSummary(): ?array
    {
        $reportable = $this->resource->reportable;

        if ($reportable === null) {
            return null;
        }

        if ($reportable instanceof \App\Models\Post) {
            return [
                'id' => $reportable->id,
                'title' => $reportable->title,
                'author' => $reportable->relationLoaded('author') && $reportable->author !== null
                    ? $reportable->author->username
                    : null,
                'status' => $reportable->status,
            ];
        }

        if ($reportable instanceof \App\Models\Comment) {
            $preview = mb_substr(trim($reportable->content), 0, 120);

            return [
                'id' => $reportable->id,
                'excerpt' => $preview,
                'post_id' => $reportable->post_id,
                'status' => $reportable->status,
            ];
        }

        return null;
    }
}
