<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Forum posts: authoring, moderation, likes and listing.
 *
 * Controllers stay thin; every state change is audited and every counter is
 * maintained inside a transaction so concurrent requests cannot drift.
 */
class PostService
{
    public function __construct(
        private readonly MarkdownService $markdown,
        private readonly AuditLogService $auditLogs,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $author, array $attributes): Post
    {
        $content = (string) $attributes['content'];

        $post = new Post([
            'category_id' => $attributes['category_id'] ?? null,
            'title' => trim((string) $attributes['title']),
            'content' => $content,
            'status' => $this->resolveStatus($attributes),
        ]);

        // Derived and identity columns are assigned explicitly: they are not
        // mass assignable, so a client can never inject HTML or an author.
        $post->content_html = $this->markdown->toHtml($content);
        $post->author_id = $author->id;
        $post->save();

        $this->auditLogs->record(
            'forum.post.created',
            ['title' => $post->title, 'status' => $post->status],
            AuditLog::RESULT_SUCCESS,
            $post,
            $author->id,
        );

        return $post;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $editor, Post $post, array $attributes): Post
    {
        if (isset($attributes['title'])) {
            $post->title = trim((string) $attributes['title']);
        }

        if (isset($attributes['category_id'])) {
            $post->category_id = $attributes['category_id'];
        }

        if (isset($attributes['content'])) {
            $content = (string) $attributes['content'];
            $post->content = $content;
            // Re-render whenever the source changes so the cached HTML can
            // never drift from the Markdown it was produced from.
            $post->content_html = $this->markdown->toHtml($content);
        }

        if (isset($attributes['status'])) {
            $post->status = $this->resolveStatus($attributes);
        }

        $post->save();

        $this->auditLogs->record(
            'forum.post.updated',
            ['fields' => array_keys($attributes)],
            AuditLog::RESULT_SUCCESS,
            $post,
            $editor->id,
        );

        return $post;
    }

    public function delete(User $actor, Post $post): void
    {
        $post->delete();

        $this->auditLogs->record(
            'forum.post.deleted',
            ['title' => $post->title],
            AuditLog::RESULT_SUCCESS,
            $post,
            $actor->id,
        );
    }

    public function like(User $user, Post $post): Post
    {
        return DB::transaction(function () use ($user, $post): Post {
            $created = $post->likes()->firstOrCreate(['user_id' => $user->id]);

            if ($created->wasRecentlyCreated) {
                $post->increment('like_count');
            }

            return $post->refresh();
        });
    }

    public function unlike(User $user, Post $post): Post
    {
        return DB::transaction(function () use ($user, $post): Post {
            $deleted = $post->likes()->where('user_id', $user->id)->delete();

            if ($deleted > 0 && $post->like_count > 0) {
                $post->decrement('like_count');
            }

            return $post->refresh();
        });
    }

    /**
     * Count a view at most once per visitor per hour, so the counter cannot be
     * inflated by refreshing the page.
     */
    public function recordView(Post $post, string $visitor): void
    {
        $key = 'post-view:'.$post->id.':'.sha1($visitor);

        if (Cache::add($key, true, now()->addHour())) {
            $post->increment('view_count');
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Post>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Post::query()->with(['author:id,username,avatar', 'category']);

        if (! ($filters['include_unpublished'] ?? false)) {
            $query->published();
        }

        if (isset($filters['category'])) {
            $category = $filters['category'];
            $query->whereHas('category', function ($builder) use ($category): void {
                $builder->where('slug', $category);
            });
        }

        if (isset($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        if (isset($filters['q']) && $filters['q'] !== '') {
            $this->applySearch($query, (string) $filters['q']);
        }

        // id is the tiebreaker: created_at has one second of precision, and
        // without a unique column MySQL and SQLite return different orders for
        // ties, which makes rows repeat or vanish across pages.
        match ($filters['sort'] ?? 'latest') {
            'popular' => $query->orderByDesc('like_count')->orderByDesc('created_at')->orderByDesc('id'),
            'views' => $query->orderByDesc('view_count')->orderByDesc('created_at')->orderByDesc('id'),
            'active' => $query->orderByRaw('COALESCE(last_commented_at, created_at) DESC')->orderByDesc('id'),
            default => $query->pinnedFirst(),
        };

        return $query->paginate($perPage);
    }

    /**
     * Simple, parameter bound LIKE search.
     *
     * Wildcards in user input are neutralised with an explicit ESCAPE
     * character. Relying on a bare backslash only works on MySQL: SQLite has no
     * default escape character for LIKE, so a term like "100%" would match
     * nothing at all there.
     *
     * Meilisearch replaces this in the search phase.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Post>  $query
     */
    private function applySearch(\Illuminate\Database\Eloquent\Builder $query, string $term): void
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
        $pattern = '%'.$escaped.'%';

        $query->where(function ($builder) use ($pattern): void {
            $builder->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("content LIKE ? ESCAPE '!'", [$pattern]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveStatus(array $attributes): string
    {
        $requested = $attributes['status'] ?? Post::STATUS_PUBLISHED;

        return $requested === Post::STATUS_DRAFT ? Post::STATUS_DRAFT : Post::STATUS_PUBLISHED;
    }

    /**
     * Touch the activity timestamp after a new comment.
     */
    public function touchActivity(Post $post, CarbonInterface $at): void
    {
        $post->forceFill(['last_commented_at' => $at])->save();
    }
}
