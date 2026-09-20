<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'parent_id' => null,
            'author_id' => User::factory(),
            'content' => fake()->sentence(12),
            'status' => Comment::STATUS_VISIBLE,
            'like_count' => 0,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Comment::STATUS_HIDDEN,
        ]);
    }
}
