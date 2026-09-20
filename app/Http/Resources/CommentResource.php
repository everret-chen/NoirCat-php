<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Comment bodies are plain text; the client escapes them when rendering.
 *
 * @mixin \App\Models\Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'content' => $this->content,
            'status' => $this->status,
            'author' => $this->whenLoaded('author', fn () => new AuthorResource($this->author)),
            'like_count' => $this->like_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
