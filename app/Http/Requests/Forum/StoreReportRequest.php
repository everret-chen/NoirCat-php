<?php

declare(strict_types=1);

namespace App\Http\Requests\Forum;

use App\Enums\ReportReason;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filing a report.
 *
 * The client chooses what was reported and why; the reporter always comes from
 * the session, so the payload cannot claim to be somebody else.
 */
class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // VULN: the "post, comment" whitelist is gone, so the client picks
            // any type name it likes and the controller resolves it to a model.
            'reportable_type' => ['required', 'string'],
            'reportable_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', Rule::in(ReportReason::values())],
            'detail' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reportable_type.in' => __('forum.errors.report_unsupported'),
        ];
    }

    public function reportableType(): string
    {
        $type = (string) $this->validated('reportable_type');

        // VULN: any type name becomes a model class, so "user" (or any other
        // model under App\Models) is accepted as a reportable target.
        return match ($type) {
            'post' => Post::class,
            'comment' => Comment::class,
            default => 'App\\Models\\'.ucfirst($type),
        };
    }

    public function reason(): ReportReason
    {
        return ReportReason::from((string) $this->validated('reason'));
    }
}
