<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ui.app_name').' · '.__('ui.app_name_en'))</title>
    {{-- Assets are built by Vite; the guard keeps pages usable before the first build. --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="flex min-h-screen flex-col">
<header class="border-b border-ink-800 bg-ink-900/80">
    <div class="mx-auto flex w-full max-w-5xl items-center gap-4 px-4 py-3">
        <a href="{{ route('home') }}" class="shrink-0 text-base font-semibold text-ink-100 hover:text-frost-300">
            {{ __('ui.app_name') }}<span class="ml-1 text-xs font-normal muted">{{ __('ui.app_name_en') }}</span>
        </a>

        <nav class="flex items-center gap-3 text-sm">
            <a href="{{ route('forum.index') }}" class="text-ink-200 hover:text-frost-300">{{ __('ui.nav.forum') }}</a>
            <span class="muted">{{ __('ui.nav.books') }}</span>
            <span class="muted">{{ __('ui.nav.guild') }}</span>
            <span class="muted">{{ __('ui.nav.learn') }}</span>
        </nav>

        <form action="{{ route('forum.index') }}" method="GET" class="ml-auto hidden sm:block">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('forum_ui.search_placeholder') }}" class="input w-52">
        </form>

        <div class="flex items-center gap-2">
            <div class="text-xs muted">
                <a href="{{ request()->fullUrlWithQuery(['lang' => 'zh_CN']) }}" class="{{ app()->getLocale() === 'zh_CN' ? 'text-frost-400' : '' }}">中文</a>
                <span class="px-0.5">/</span>
                <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" class="{{ app()->getLocale() === 'en' ? 'text-frost-400' : '' }}">EN</a>
            </div>

            @auth
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="btn-ghost">
                        {{ auth()->user()->username }}
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute right-0 z-20 mt-2 w-48 rounded-md border border-ink-800 bg-ink-900 p-1 text-sm shadow-xl">
                        <a href="{{ route('forum.create') }}" class="block rounded px-3 py-2 text-ink-200 hover:bg-ink-850">{{ __('forum_ui.new_post') }}</a>
                        <a href="{{ route('profile.edit') }}" class="block rounded px-3 py-2 text-ink-200 hover:bg-ink-850">{{ __('profile_ui.title') }}</a>
                        <a href="{{ route('sessions.index') }}" class="block rounded px-3 py-2 text-ink-200 hover:bg-ink-850">{{ __('profile_ui.sessions') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full rounded px-3 py-2 text-left text-ink-200 hover:bg-ink-850">{{ __('auth_ui.logout') }}</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn-ghost">{{ __('auth_ui.login') }}</a>
                <a href="{{ route('register') }}" class="btn-primary">{{ __('auth_ui.register') }}</a>
            @endauth
        </div>
    </div>
</header>

<main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
    @include('partials.flash')
    @yield('content')
</main>

<footer class="border-t border-ink-800 py-6 text-center text-xs muted">
    {{ __('ui.app_name') }} / {{ __('ui.app_name_en') }} · {{ __('ui.tagline') }}
</footer>
</body>
</html>
