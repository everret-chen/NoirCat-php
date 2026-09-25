<?php

declare(strict_types=1);

namespace App\Http\Requests\Forum;

use App\Enums\ReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Closing a report: a note is optional but recorded when present.
 */
class HandleReportRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:500'],
            // Accepted so a client can be explicit; the endpoint itself decides
            // between resolved and dismissed.
            'status' => ['sometimes', Rule::in([
                ReportStatus::RESOLVED->value,
                ReportStatus::DISMISSED->value,
            ])],
        ];
    }
}
