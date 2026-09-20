<?php

declare(strict_types=1);

namespace App\Http\Requests\Forum;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof Post && $this->user()?->can('update', $post) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:2', 'max:200'],
            'content' => ['sometimes', 'string', 'min:2', 'max:50000'],
            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('type', Category::TYPE_POST),
            ],
            'status' => ['sometimes', Rule::in([Post::STATUS_DRAFT, Post::STATUS_PUBLISHED])],
        ];
    }
}
