@extends('layouts.app')

@section('title', __('ui.app_name').' · '.__('ui.app_name_en'))

@section('content')
    <section class="mb-10">
        <h1 class="text-3xl font-semibold tracking-tight">{{ __('ui.app_name') }} <span class="muted text-xl">{{ __('ui.app_name_en') }}</span></h1>
        <p class="mt-2 muted">{{ __('ui.tagline') }} · 中英双语网络安全学习社区</p>
    </section>

    <section class="mb-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (['forum', 'books', 'guild', 'learn'] as $module)
            <div class="card">
                <div class="text-sm font-medium">{{ __('ui.nav.'.$module) }}</div>
                @if ($module === 'forum')
                    <a href="{{ route('forum.index') }}" class="mt-2 inline-block text-xs">{{ __('ui.nav.forum') }} →</a>
                @else
                    <div class="mt-2 text-xs muted">Phase 3+</div>
                @endif
            </div>
        @endforeach
    </section>

    <section>
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-medium">{{ __('ui.nav.forum') }}</h2>
            <a href="{{ route('forum.index') }}" class="text-sm">{{ __('ui.nav.forum') }} →</a>
        </div>

        @forelse ($posts as $post)
            <article class="mb-3 rounded-lg border border-ink-800 bg-ink-900/60 p-4">
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('forum.show', $post) }}" class="text-base font-medium text-ink-100 hover:text-frost-300">{{ $post->title }}</a>
                        <p class="mt-1 line-clamp-2 text-sm muted">{{ Str::limit(trim(strip_tags((string) $post->content_html)), 140) }}</p>
                    </div>
                    <div class="shrink-0 text-right text-xs muted">
                        <div>{{ $post->author?->username }}</div>
                        <div>{{ optional($post->created_at)->diffForHumans() }}</div>
                    </div>
                </div>
            </article>
        @empty
            <p class="muted">{{ __('forum_ui.empty') }}</p>
        @endforelse
    </section>
@endsection
