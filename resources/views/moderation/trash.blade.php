@extends('layouts.app')

@section('title', __('moderation_ui.trash.title').' · '.__('ui.app_name'))

@section('content')
    <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ __('moderation_ui.trash.title') }}</h1>
            <p class="mt-1 text-sm muted">
                {{ $canSeeEverything ? __('moderation_ui.trash.subtitle_all') : __('moderation_ui.trash.subtitle_own') }}
            </p>
        </div>

        <a href="{{ route('moderation.reports') }}" class="btn-ghost">{{ __('moderation_ui.reports.title') }}</a>
    </header>

    <section class="mb-8">
        <h2 class="mb-3 text-lg font-medium">{{ __('moderation_ui.trash.posts') }}</h2>

        @forelse ($posts as $post)
            <article class="card mb-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-medium">{{ $post->title }}</p>
                    <p class="mt-1 text-xs muted">
                        {{ __('moderation_ui.trash.belongs_to', ['name' => $post->author?->username ?? '—']) }}
                        · {{ __('moderation_ui.trash.deleted_at', ['time' => optional($post->deleted_at)->diffForHumans()]) }}
                    </p>
                </div>

                <form method="POST" action="{{ route('moderation.trash.posts.restore', $post->id) }}">
                    @csrf
                    <button type="submit" class="btn-ghost">{{ __('moderation_ui.trash.restore') }}</button>
                </form>
            </article>
        @empty
            <p class="muted">{{ __('moderation_ui.trash.empty_posts') }}</p>
        @endforelse

        <div class="mt-3">{{ $posts->links() }}</div>
    </section>

    <section>
        <h2 class="mb-3 text-lg font-medium">{{ __('moderation_ui.trash.comments') }}</h2>

        @forelse ($comments as $comment)
            <article class="card mb-2 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="whitespace-pre-line text-sm">{{ \Illuminate\Support\Str::limit($comment->content, 160) }}</p>
                    <p class="mt-1 text-xs muted">
                        {{ __('moderation_ui.trash.belongs_to', ['name' => $comment->author?->username ?? '—']) }}
                        · {{ __('moderation_ui.trash.deleted_at', ['time' => optional($comment->deleted_at)->diffForHumans()]) }}
                        @if ($comment->post)
                            · {{ $comment->post->title }}
                        @endif
                    </p>
                </div>

                <form method="POST" action="{{ route('moderation.trash.comments.restore', $comment->id) }}">
                    @csrf
                    <button type="submit" class="btn-ghost">{{ __('moderation_ui.trash.restore') }}</button>
                </form>
            </article>
        @empty
            <p class="muted">{{ __('moderation_ui.trash.empty_comments') }}</p>
        @endforelse

        <div class="mt-3">{{ $comments->links() }}</div>
    </section>
@endsection
