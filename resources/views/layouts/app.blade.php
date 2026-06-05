<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
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
    @include('partials.sidebar')

    <header class="fixed top-0 z-50 h-14 w-full border-b border-gray-200 bg-white/95 shadow-sm backdrop-blur lg:pl-72">
        <div class="flex h-full items-center justify-between gap-3 px-4">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    id="sidebar-toggle"
                    type="button"
                    class="rounded-lg p-2 text-[#0B318F] transition-colors hover:bg-gray-100 lg:hidden"
                    aria-label="Open sidebar"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="flex min-w-0 items-center gap-2 text-[#0B318F]">
                    <svg class="h-5 w-5 shrink-0 text-[#00A3E6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    <span class="truncate text-sm font-semibold sm:text-base">Skolaris Pulse</span>
                </div>
            </div>

            <div class="hidden items-center gap-3 sm:flex">
                <div class="text-right">
                    <p class="max-w-[160px] truncate text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                    @if (auth()->user()->role)
                        <p class="text-xs text-gray-500">{{ auth()->user()->role->name }}</p>
                    @endif
                </div>
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#00A3E6] text-xs font-bold text-white">
                    {{ $initials ?: 'U' }}
                </div>
            </div>
        </div>
    </header>

    <div class="min-h-screen pt-14 transition-all duration-300 lg:pl-72">
        <main class="overflow-x-hidden p-4 sm:p-6">
            <div class="page-shell">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
