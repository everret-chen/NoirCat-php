@extends('layouts.app')

@section('title', __('auth_ui.register').' · '.__('ui.app_name'))

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-6 text-xl font-semibold">{{ __('auth_ui.create_account') }}</h1>

        <form method="POST" action="{{ route('register.store') }}" class="card space-y-4">
            @csrf

            <div>
                <label class="label" for="username">{{ __('auth_ui.username') }}</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" class="input" required autofocus autocomplete="username">
                <p class="mt-1 text-xs muted">3-32 位字母、数字、下划线或短横线</p>
                @error('username')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label" for="email">{{ __('auth_ui.email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" required autocomplete="email">
                @error('email')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label" for="password">{{ __('auth_ui.password') }}</label>
                <input id="password" name="password" type="password" class="input" required autocomplete="new-password">
                <p class="mt-1 text-xs muted">至少 8 位，需包含字母与数字</p>
                @error('password')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label" for="password_confirmation">{{ __('auth_ui.password_confirmation') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn-primary w-full">{{ __('auth_ui.register') }}</button>

            <p class="text-center text-xs muted">
                {{ __('auth_ui.have_account') }}
                <a href="{{ route('login') }}">{{ __('auth_ui.login') }}</a>
            </p>
        </form>
    </div>
@endsection
