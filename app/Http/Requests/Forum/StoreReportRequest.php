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
            'reportable_type' => ['required', 'string', Rule::in(['post', 'comment'])],
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

        return $type === 'post' ? Post::class : Comment::class;
    }

    public function reason(): ReportReason
    {
        return ReportReason::from((string) $this->validated('reason'));
    }
}
