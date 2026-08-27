<!DOCTYPE html>
@php
    $isDesktopApp = (bool) config('nativephp-internal.running', env('NATIVEPHP_RUNNING', false));
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-pulse-desktop="{{ $isDesktopApp ? '1' : '0' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <script>
        (function () {
            document.documentElement.dataset.sidebar = window.innerWidth >= 1024 ? 'open' : 'closed';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $initials = collect(explode(' ', auth()->user()->name))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->join('');
@endphp
<body class="min-h-screen bg-slate-50">
    @include('partials.full-screen-loader')
    @include('partials.full-screen-loader-fallback')
    @include('partials.sidebar')
    @include('partials.desktop-updater-overlay')
    @include('partials.desktop-installer-update-modal')
    @include('partials.document-preview-engine-modal')

    <header class="fixed top-0 z-50 h-14 w-full border-b border-gray-200 bg-white/95 shadow-sm backdrop-blur">
        <div class="flex h-full items-center justify-between gap-3 px-4">
            <div id="header-leading" class="flex min-w-0 items-center gap-3">
                <button
                    id="sidebar-toggle"
                    type="button"
                    class="rounded-lg p-2 text-[#0B318F] transition-colors hover:bg-gray-100"
                    aria-label="Toggle sidebar"
                    aria-expanded="false"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div id="header-brand" class="flex min-w-0 items-center gap-2 text-[#0B318F]">
                    <svg class="h-5 w-5 shrink-0 text-[#00A3E6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    <span class="truncate text-sm font-semibold sm:text-base">People360</span>
                </div>
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-2 sm:gap-3">
                <div class="hidden items-center gap-3 sm:flex">
                    <div class="text-right">
                        <p class="max-w-[160px] truncate text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                        @if (auth()->user()->roles->isNotEmpty())
                            <p class="text-xs text-gray-500">{{ auth()->user()->roleLabel() }}</p>
                        @endif
                    </div>
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#00A3E6] text-xs font-bold text-white">
                        {{ $initials ?: 'U' }}
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-[#0B318F] transition-colors hover:bg-gray-100" title="Log out">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span class="hidden md:inline">Log out</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="app-main-offset min-h-screen pt-14 transition-[margin] duration-300 ease-in-out">
        <main class="overflow-x-hidden p-4 sm:p-6">
            <div class="page-shell">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
