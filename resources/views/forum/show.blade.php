@extends('layouts.app')

@section('title', $post->title.' · '.__('ui.app_name'))

@section('content')
    <article class="mb-8">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            @if ($post->is_pinned)
                <span class="badge-pin">{{ __('ui.status.pinned') }}</span>
            @endif
            @if ($post->is_featured)
                <span class="badge-featured">{{ __('ui.status.featured') }}</span>
            @endif
            @if ($post->is_locked)
                <span class="badge-locked">{{ __('ui.status.locked') }}</span>
            @endif
            @if (! $post->isPublished())
                <span class="badge-draft">{{ __('ui.status.draft') }}</span>
            @endif
            @if ($post->category)
                <a href="{{ route('forum.index', ['category' => $post->category->slug]) }}" class="badge bg-ink-800">{{ $post->category->displayName() }}</a>
            @endif
        </div>

        <h1 class="text-2xl font-semibold tracking-tight">{{ $post->title }}</h1>

        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs muted">
            <span>{{ __('forum_ui.by') }} {{ $post->author?->username }}</span>
            <span>{{ optional($post->created_at)->diffForHumans() }}</span>
            <span>{{ $post->view_count }} {{ __('ui.stats.views') }}</span>
            <span>{{ $post->like_count }} {{ __('ui.stats.likes') }}</span>
            <span>{{ $post->comment_count }} {{ __('ui.stats.comments') }}</span>
        </div>

        {{-- content_html is rendered from Markdown and passed through HTMLPurifier on write. --}}
        <div class="prose-forum mt-6">{!! $post->content_html !!}</div>

        <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-ink-800 pt-4">
            @auth
                @if ($likedByMe)
                    <form method="POST" action="{{ route('forum.unlike', $post) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-ghost">{{ __('forum_ui.unlike') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('forum.like', $post) }}">
                        @csrf
                        <button type="submit" class="btn-ghost">{{ __('forum_ui.like') }}</button>
                    </form>
                @endif

                @can('update', $post)
                    <a href="{{ route('forum.edit', $post) }}" class="btn-ghost">{{ __('ui.action.edit') }}</a>
                @endcan

                @can('delete', $post)
                    <form method="POST" action="{{ route('forum.destroy', $post) }}" onsubmit="return confirm('{{ __('ui.action.confirm_delete') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger">{{ __('ui.action.delete') }}</button>
                    </form>
                @endcan

                {{-- Reporting is for everyone else's content. --}}
                @if ($post->author_id !== auth()->id())
                    @include('forum._report', [
                        'action' => route('forum.report', $post),
                        'alreadyReported' => in_array($post->id, $myOpenReports['post'] ?? [], true),
                        'scope' => 'post-'.$post->id,
                    ])
                @endif
            @endauth
        </div>

        {{-- Moderation lives behind a disclosure so the author actions above stay
             the obvious path for ordinary members. --}}
        @canany(['pin', 'feature', 'lock', 'move'], $post)
            <div x-data="{ open: false }" class="mt-4 rounded-lg border border-ink-800 bg-ink-900/50 p-4">
                <button type="button" @click="open = !open" class="text-sm text-ink-200 hover:text-frost-300">
                    {{ __('moderation_ui.post.toolbar') }} <span x-text="open ? '▲' : '▼'"></span>
                </button>

                <div x-show="open" x-cloak class="mt-3 flex flex-wrap items-end gap-2">
                    @can('pin', $post)
                        <form method="POST" action="{{ route('moderation.forum.pin', $post) }}">
                            @csrf
                            <button type="submit" class="btn-ghost">{{ $post->is_pinned ? __('forum_ui.unpin') : __('forum_ui.pin') }}</button>
                        </form>
                    @endcan

                    @can('feature', $post)
                        <form method="POST" action="{{ route('moderation.forum.feature', $post) }}">
                            @csrf
                            <button type="submit" class="btn-ghost">{{ $post->is_featured ? __('moderation_ui.post.unfeature') : __('moderation_ui.post.feature') }}</button>
                        </form>
                    @endcan

                    @can('lock', $post)
                        <form method="POST" action="{{ route('moderation.forum.lock', $post) }}">
                            @csrf
                            <button type="submit" class="btn-ghost">{{ $post->is_locked ? __('moderation_ui.post.unlock') : __('moderation_ui.post.lock') }}</button>
                        </form>
                    @endcan

                    @can('move', $post)
                        <form method="POST" action="{{ route('moderation.forum.move', $post) }}" class="flex items-end gap-2">
                            @csrf
                            @method('PUT')
                            <div>
                                <label for="move-category" class="label">{{ __('moderation_ui.post.move') }}</label>
                                <select id="move-category" name="category_id" class="input w-48">
                                    <option value="">{{ __('forum_ui.no_category') }}</option>
                                    @foreach ($moveTargets as $target)
                                        <option value="{{ $target->id }}" @selected($post->category_id === $target->id)>{{ $target->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn-ghost">{{ __('moderation_ui.post.move_submit') }}</button>
                        </form>
                    @endcan
                </div>
            </div>
        @endcanany
    </article>

    <section>
        <h2 class="mb-4 text-lg font-medium">{{ __('forum_ui.comments_title') }} ({{ $post->comment_count }})</h2>

        @auth
            @if ($post->is_locked)
                <p class="mb-6 rounded-md border border-ink-800 bg-ink-900/60 px-4 py-3 text-sm muted">
                    {{ __('moderation_ui.post.locked_notice') }}
                </p>
            @else
                <form method="POST" action="{{ route('forum.comments.store', $post) }}" class="mb-6 space-y-2">
                    @csrf
                    <textarea name="content" rows="3" class="input" placeholder="{{ __('forum_ui.comment_placeholder') }}" required></textarea>
                    @error('content')
                        <p class="text-xs text-red-400">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="btn-primary">{{ __('forum_ui.post_comment') }}</button>
                </form>
            @endif
        @else
            <p class="mb-6 text-sm muted">
                <a href="{{ route('login') }}">{{ __('auth_ui.login') }}</a> {{ __('forum_ui.login_to_comment') }}
            </p>
        @endauth

        @forelse ($topLevelComments as $comment)
            @include('forum._comment', [
                'comment' => $comment,
                'byParent' => $byParent,
                'depth' => 1,
                'maxDepth' => $maxDepth,
                'post' => $post,
                'reportedComments' => $myOpenReports['comments'] ?? [],
            ])
        @empty
            <p class="muted">{{ __('forum_ui.no_comments') }}</p>
        @endforelse
    </section>

    <p class="mt-8 text-sm">
        <a href="{{ route('forum.index') }}">← {{ __('ui.action.back') }}</a>
    </p>
@endsection
