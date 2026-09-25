<?php

declare(strict_types=1);

namespace App\Http\Requests\Forum;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Moving a thread to another section (or out of every section).
 */
class MovePostRequest extends FormRequest
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
            'category_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('type', Category::TYPE_POST),
            ],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function category(): ?Category
    {
        $id = $this->validated('category_id');

        return $id === null ? null : Category::query()->whereKey($id)->first();
    }
}
