@extends('layouts.app')

@section('title', ($search !== '' ? __('forum_ui.search_results', ['q' => $search]) : __('forum_ui.title')).' · '.__('ui.app_name'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <h1 class="text-xl font-semibold">{{ __('forum_ui.title') }}</h1>
        @auth
            <a href="{{ route('forum.create') }}" class="btn-primary ml-auto">{{ __('forum_ui.new_post') }}</a>
        @endauth
    </div>

    @if ($search !== '')
        <p class="mb-4 text-sm muted">
            {{ __('forum_ui.search_results', ['q' => $search]) }}
            <a href="{{ route('forum.index') }}" class="ml-2">{{ __('forum_ui.clear_search') }}</a>
        </p>
    @endif

    <form method="GET" action="{{ route('forum.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="category">{{ __('forum_ui.filter_category') }}</label>
            <select id="category" name="category" class="input w-40">
                <option value="">{{ __('ui.all_categories') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->slug }}" @selected($categorySlug === $category->slug)>{{ $category->displayName() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="label" for="sort">{{ __('ui.sort.latest') }}</label>
            <select id="sort" name="sort" class="input w-40">
                @foreach (['latest', 'active', 'popular', 'views'] as $option)
                    <option value="{{ $option }}" @selected($sort === $option)>{{ __('ui.sort.'.$option) }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-40 flex-1">
            <label class="label" for="q">{{ __('ui.action.search') }}</label>
            <input id="q" name="q" type="search" value="{{ $search }}" class="input" placeholder="{{ __('forum_ui.search_placeholder') }}">
        </div>

        <button type="submit" class="btn-ghost">{{ __('ui.action.search') }}</button>
    </form>

    <div class="space-y-3">
        @forelse ($posts as $post)
            <article class="rounded-lg border border-ink-800 bg-ink-900/60 p-4">
                <div class="flex items-start gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($post->is_pinned)
                                <span class="badge-pin">{{ __('ui.status.pinned') }}</span>
                            @endif
                            @if (! $post->isPublished())
                                <span class="badge-draft">{{ __('ui.status.draft') }}</span>
                            @endif
                            <a href="{{ route('forum.show', $post) }}" class="font-medium text-ink-100 hover:text-frost-300">{{ $post->title }}</a>
                        </div>

                        <p class="mt-1.5 line-clamp-2 text-sm muted">{{ Str::limit(trim(strip_tags((string) $post->content_html)), 160) }}</p>

                        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs muted">
                            <span>{{ $post->category?->displayName() }}</span>
                            <span>{{ __('forum_ui.by') }} {{ $post->author?->username }}</span>
                            <span>{{ optional($post->created_at)->diffForHumans() }}</span>
                        </div>
                    </div>

                    <div class="shrink-0 text-right text-xs muted">
                        <div>{{ $post->view_count }} {{ __('ui.stats.views') }}</div>
                        <div>{{ $post->like_count }} {{ __('ui.stats.likes') }}</div>
                        <div>{{ $post->comment_count }} {{ __('ui.stats.comments') }}</div>
                    </div>
                </div>
            </article>
        @empty
            <p class="muted">{{ $search !== '' ? __('ui.empty') : __('forum_ui.empty') }}</p>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $posts->links() }}
    </div>
@endsection
