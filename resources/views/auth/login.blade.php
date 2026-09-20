@extends('layouts.app')

@section('title', __('auth_ui.login').' · '.__('ui.app_name'))

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-6 text-xl font-semibold">{{ __('auth_ui.welcome_back') }}</h1>

        <form method="POST" action="{{ route('login.store') }}" class="card space-y-4">
            @csrf

            <div>
                <label class="label" for="username">{{ __('auth_ui.username') }} / {{ __('auth_ui.email') }}</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" class="input" required autofocus autocomplete="username">
                @error('username')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label" for="password">{{ __('auth_ui.password') }}</label>
                <input id="password" name="password" type="password" class="input" required autocomplete="current-password">
                @error('password')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm muted">
                <input type="checkbox" name="remember" value="1" class="rounded border-ink-700 bg-ink-900">
                {{ __('auth_ui.remember_me') }}
            </label>

            <button type="submit" class="btn-primary w-full">{{ __('auth_ui.login') }}</button>

            <p class="text-center text-xs muted">
                {{ __('auth_ui.no_account') }}
                <a href="{{ route('register') }}">{{ __('auth_ui.register') }}</a>
            </p>
        </form>
    </div>
@endsection
