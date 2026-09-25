<?php

declare(strict_types=1);

namespace App\Http\Requests\Forum;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Blade reporting form. The target comes from the route, so only the reason
 * and the optional note travel in the body.
 */
class ReportContentRequest extends FormRequest
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
            'reason' => ['required', 'string', Rule::in(ReportReason::values())],
            'detail' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function reason(): ReportReason
    {
        return ReportReason::from((string) $this->validated('reason'));
    }
}
