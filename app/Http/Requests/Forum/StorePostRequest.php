<?php

declare(strict_types=1);

namespace App\Http\Requests\Forum;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can(Permission::POST_CREATE->value);
    }

    /**
     * author_id is intentionally absent: the author always comes from the
     * authenticated session, never from the request body.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'content' => ['required', 'string', 'min:2', 'max:50000'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('type', Category::TYPE_POST),
            ],
            'status' => ['sometimes', Rule::in([Post::STATUS_DRAFT, Post::STATUS_PUBLISHED])],
        ];
    }
}
