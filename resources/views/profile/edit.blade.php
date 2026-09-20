@extends('layouts.app')

@section('title', __('profile_ui.title').' · '.__('ui.app_name'))

@section('content')
    <div class="mx-auto max-w-2xl space-y-6">
        <h1 class="text-xl font-semibold">{{ __('profile_ui.title') }}</h1>

        @if (! auth()->user()->hasVerifiedEmail())
            <div class="rounded-md border border-amber-900/60 bg-amber-950/30 px-4 py-3 text-sm text-amber-200">
                <p>{{ __('auth_ui.verify_email_notice') }}</p>
                <form method="POST" action="{{ route('verification.send') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn-ghost">{{ __('auth_ui.resend_verification') }}</button>
                </form>
            </div>
        @endif

        <section class="card">
            <h2 class="mb-4 text-sm font-medium">{{ __('profile_ui.avatar') }}</h2>
            <div class="flex items-center gap-4">
                @if (auth()->user()->avatar)
                    <img src="{{ Storage::disk('public')->url(auth()->user()->avatar) }}" alt="avatar" class="h-16 w-16 rounded-full object-cover">
                @else
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-ink-800 text-lg">{{ mb_substr(auth()->user()->username, 0, 1) }}</div>
                @endif
                <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="block text-xs" required>
                    <p class="text-xs muted">{{ __('profile_ui.avatar_hint') }}</p>
                    @error('avatar')
                        <p class="text-xs text-red-400">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="btn-ghost">{{ __('profile_ui.upload_avatar') }}</button>
                </form>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-sm font-medium">{{ __('auth_ui.email') }}</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="label" for="email">{{ __('auth_ui.email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" class="input" required>
                    @error('email')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="border-t border-ink-800 pt-4">
                    <p class="mb-3 text-xs muted">{{ __('auth_ui.change_password_hint') }}</p>

                    <div class="space-y-3">
                        <div>
                            <label class="label" for="current_password">{{ __('auth_ui.current_password') }}</label>
                            <input id="current_password" name="current_password" type="password" class="input" autocomplete="current-password">
                            @error('current_password')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="password">{{ __('auth_ui.new_password') }}</label>
                            <input id="password" name="password" type="password" class="input" autocomplete="new-password">
                            @error('password')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="password_confirmation">{{ __('auth_ui.password_confirmation') }}</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary">{{ __('ui.action.save') }}</button>
            </form>
        </section>

        <p class="text-sm muted">
            <a href="{{ route('sessions.index') }}">{{ __('profile_ui.sessions') }} →</a>
        </p>
    </div>
@endsection
