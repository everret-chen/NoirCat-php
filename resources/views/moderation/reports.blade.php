@extends('layouts.app')

@section('title', __('moderation_ui.reports.title').' · '.__('ui.app_name'))

@section('content')
    <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ __('moderation_ui.reports.title') }}</h1>
            <p class="mt-1 text-sm muted">{{ __('moderation_ui.reports.subtitle', ['count' => $pendingCount]) }}</p>
        </div>

        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('moderation.trash') }}" class="btn-ghost">{{ __('moderation_ui.nav.trash') }}</a>
        </div>
    </header>

    <form method="GET" action="{{ route('moderation.reports') }}" class="card mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label for="status" class="label">{{ __('moderation_ui.reports.filter_status') }}</label>
            <select id="status" name="status" class="input w-40">
                @foreach (\App\Enums\ReportStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                @endforeach
                <option value="all" @selected($status === 'all')>{{ __('moderation_ui.reports.status_all') }}</option>
            </select>
        </div>

        <div>
            <label for="reason" class="label">{{ __('moderation_ui.reports.filter_reason') }}</label>
            <select id="reason" name="reason" class="input w-44">
                <option value="">{{ __('moderation_ui.reports.status_all') }}</option>
                @foreach (\App\Enums\ReportReason::cases() as $case)
                    <option value="{{ $case->value }}" @selected($reason === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn-primary">{{ __('ui.action.search') }}</button>
    </form>

    @forelse ($reports as $report)
        <article class="card mb-3">
            <div class="flex flex-wrap items-center gap-2 text-xs muted">
                <span class="badge {{ $report->status === \App\Enums\ReportStatus::PENDING ? 'badge-locked' : 'bg-ink-700' }}">
                    {{ $report->status->label() }}
                </span>
                <span class="badge bg-ink-800">{{ $report->reason->label() }}</span>
                <span>{{ __('moderation_ui.reports.reporter') }} {{ $report->reporter?->username ?? '—' }}</span>
                <span>{{ __('moderation_ui.reports.reported_at') }} {{ optional($report->created_at)->diffForHumans() }}</span>
            </div>

            <div class="mt-3 text-sm">
                @if ($report->reportable === null)
                    <p class="muted">{{ __('moderation_ui.reports.content_gone') }}</p>
                @elseif ($report->reportable instanceof \App\Models\Post)
                    <p class="text-xs muted">{{ __('moderation_ui.reports.target_post') }}</p>
                    <p class="mt-1">
                        <a href="{{ route('forum.show', $report->reportable_id) }}" class="font-medium">{{ $report->reportable->title }}</a>
                        <span class="muted">· {{ $report->reportable->author?->username }}</span>
                    </p>
                @else
                    <p class="text-xs muted">{{ __('moderation_ui.reports.target_comment') }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($report->reportable->content, 200) }}</p>
                    <p class="mt-1">
                        <a href="{{ route('forum.show', $report->reportable->post_id) }}">{{ __('moderation_ui.reports.view_content') }} →</a>
                    </p>
                @endif

                @if ($report->detail)
                    <p class="mt-2 rounded-md border border-ink-800 bg-ink-950/60 px-3 py-2 text-xs">{{ $report->detail }}</p>
                @endif
            </div>

            @if ($report->isOpen())
                <div class="mt-3 flex flex-wrap items-end gap-2 border-t border-ink-800 pt-3">
                    <form method="POST" action="{{ route('moderation.reports.resolve', $report) }}" class="flex flex-1 flex-wrap items-end gap-2">
                        @csrf
                        <div class="min-w-56 flex-1">
                            <label for="note-{{ $report->id }}" class="label">{{ __('moderation_ui.reports.note_placeholder') }}</label>
                            <input id="note-{{ $report->id }}" name="note" maxlength="500" class="input">
                        </div>
                        <button type="submit" class="btn-primary">{{ __('moderation_ui.reports.resolve') }}</button>
                    </form>

                    <form method="POST" action="{{ route('moderation.reports.dismiss', $report) }}">
                        @csrf
                        <button type="submit" class="btn-ghost">{{ __('moderation_ui.reports.dismiss') }}</button>
                    </form>
                </div>
            @else
                <p class="mt-3 border-t border-ink-800 pt-3 text-xs muted">
                    {{ __('moderation_ui.reports.handled_by', ['name' => $report->handler?->username ?? '—']) }}
                    @if ($report->resolution_note)
                        · {{ $report->resolution_note }}
                    @endif
                </p>
            @endif
        </article>
    @empty
        <p class="muted">{{ __('moderation_ui.reports.empty') }}</p>
    @endforelse

    <div class="mt-6">
        {{ $reports->links() }}
    </div>
@endsection
