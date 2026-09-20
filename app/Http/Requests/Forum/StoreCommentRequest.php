<?php

declare(strict_types=1);

namespace App\Http\Requests\Forum;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can(Permission::COMMENT_CREATE->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:5000'],
            'parent_id' => ['nullable', 'integer'],
        ];
    }
}
