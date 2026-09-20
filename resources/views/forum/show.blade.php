@extends('layouts.app')

@section('title', $post->title.' · '.__('ui.app_name'))

@section('content')
    <article class="mb-8">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            @if ($post->is_pinned)
                <span class="badge-pin">{{ __('ui.status.pinned') }}</span>
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

                @can('pin', $post)
                    <form method="POST" action="{{ route('forum.pin', $post) }}">
                        @csrf
                        <button type="submit" class="btn-ghost">{{ $post->is_pinned ? __('forum_ui.unpin') : __('forum_ui.pin') }}</button>
                    </form>
                @endcan

                @can('delete', $post)
                    <form method="POST" action="{{ route('forum.destroy', $post) }}" onsubmit="return confirm('{{ __('ui.action.confirm_delete') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger">{{ __('ui.action.delete') }}</button>
                    </form>
                @endcan
            @endauth
        </div>
    </article>

    <section>
        <h2 class="mb-4 text-lg font-medium">{{ __('forum_ui.comments_title') }} ({{ $post->comment_count }})</h2>

        @auth
            <form method="POST" action="{{ route('forum.comments.store', $post) }}" class="mb-6 space-y-2">
                @csrf
                <textarea name="content" rows="3" class="input" placeholder="{{ __('forum_ui.comment_placeholder') }}" required></textarea>
                @error('content')
                    <p class="text-xs text-red-400">{{ $message }}</p>
                @enderror
                <button type="submit" class="btn-primary">{{ __('forum_ui.post_comment') }}</button>
            </form>
        @else
            <p class="mb-6 text-sm muted">
                <a href="{{ route('login') }}">{{ __('auth_ui.login') }}</a> {{ __('forum_ui.login_to_comment') }}
            </p>
        @endauth

        @forelse ($topLevelComments as $comment)
            @include('forum._comment', ['comment' => $comment, 'byParent' => $byParent, 'depth' => 1, 'maxDepth' => $maxDepth])
        @empty
            <p class="muted">{{ __('forum_ui.no_comments') }}</p>
        @endforelse
    </section>

    <p class="mt-8 text-sm">
        <a href="{{ route('forum.index') }}">← {{ __('ui.action.back') }}</a>
    </p>
@endsection
