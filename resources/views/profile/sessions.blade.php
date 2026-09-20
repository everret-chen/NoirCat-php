@extends('layouts.app')

@section('title', __('profile_ui.sessions').' · '.__('ui.app_name'))

@section('content')
    <div class="mx-auto max-w-2xl space-y-5">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ __('profile_ui.sessions') }}</h1>
            <form method="POST" action="{{ route('sessions.revokeOthers') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger">{{ __('profile_ui.revoke_others') }}</button>
            </form>
        </div>

        <p class="text-xs muted">{{ __('profile_ui.sessions_hint') }}</p>

        <div class="space-y-3">
            @forelse ($sessions as $session)
                <div class="flex items-center justify-between rounded-lg border border-ink-800 bg-ink-900/60 px-4 py-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="truncate">{{ $session->name }}</span>
                            @if ($session->getKey() === $currentTokenId)
                                <span class="badge bg-frost-600/20 text-frost-300">{{ __('profile_ui.current') }}</span>
                            @endif
                        </div>
                        <div class="mt-1 text-xs muted">
                            {{ __('profile_ui.last_used') }}: {{ optional($session->last_used_at)->diffForHumans() ?? '—' }}
                            · {{ __('profile_ui.created') }}: {{ optional($session->created_at)->diffForHumans() }}
                        </div>
                    </div>

                    <form method="POST" action="{{ route('sessions.destroy', $session->getKey()) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-ghost">{{ __('profile_ui.revoke') }}</button>
                    </form>
                </div>
            @empty
                <p class="muted">{{ __('profile_ui.no_sessions') }}</p>
            @endforelse
        </div>
    </div>
@endsection
