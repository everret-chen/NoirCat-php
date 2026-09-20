<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin \App\Models\Post
 */
class PostResource extends JsonResource
{
    /**
     * Detail responses include the Markdown source and the purified HTML; list
     * responses only carry the excerpt to keep payloads small.
     */
    private bool $withBody = false;

    public function withBody(): self
    {
        $this->withBody = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => Str::limit(trim(strip_tags((string) $this->content_html)), 160),
            'content' => $this->when($this->withBody, $this->content),
            'content_html' => $this->when($this->withBody, $this->content_html),
            'status' => $this->status,
            'is_pinned' => $this->is_pinned,
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'author' => $this->whenLoaded('author', fn () => new AuthorResource($this->author)),
            'view_count' => $this->view_count,
            'like_count' => $this->like_count,
            'comment_count' => $this->comment_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
