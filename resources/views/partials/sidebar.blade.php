@php
    $navItems = [
        ['name' => 'Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard', 'admin' => false],
        ['name' => 'User Management', 'route' => 'users.index', 'pattern' => 'users.*', 'admin' => true],
        ['name' => 'Role Management', 'route' => 'roles.index', 'pattern' => 'roles.*', 'admin' => true],
    ];
@endphp

<div id="sidebar-overlay" class="fixed inset-0 z-[55] hidden bg-black/50 lg:hidden" aria-hidden="true"></div>

<aside
    id="app-sidebar"
    class="fixed left-0 top-0 z-[60] flex h-full w-72 -translate-x-full flex-col bg-[#0B318F] text-white shadow-xl transition-transform duration-300 ease-in-out lg:translate-x-0 lg:z-50"
>
    <div class="flex h-14 shrink-0 items-center justify-between border-b border-white/10 px-4">
        <div class="flex min-w-0 items-center gap-2">
            <img src="{{ asset('img/skolarislogo.png') }}" alt="Skolaris" class="h-8 w-auto shrink-0 brightness-0 invert">
            <div class="min-w-0">
                <p class="flex items-center gap-1 truncate text-sm font-bold">
                    <svg class="h-3.5 w-3.5 shrink-0 text-[#00A3E6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    Pulse
                </p>
                <p class="truncate text-[10px] text-white/60">ISKOLARIS Desktop</p>
            </div>
        </div>
        <button id="sidebar-close" type="button" class="shrink-0 rounded-lg p-1 text-white hover:bg-white/10 lg:hidden" aria-label="Isara ang sidebar">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-2 py-3">
        <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-wider text-white/40">Administration</p>
        <ul class="space-y-0.5">
            @foreach ($navItems as $item)
                @if ($item['admin'] && ! auth()->user()->isAdmin())
                    @continue
                @endif
                @php $active = request()->routeIs($item['pattern']); @endphp
                <li>
                    <a
                        href="{{ route($item['route']) }}"
                        data-sidebar-close
                        class="sidebar-link {{ $active ? 'sidebar-link-active' : '' }}"
                    >
                        @if ($item['route'] === 'dashboard')
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        @elseif ($item['route'] === 'users.index')
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        @else
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        @endif
                        {{ $item['name'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="shrink-0 space-y-2 border-t border-white/10 p-3">
        <p class="truncate px-2 text-xs text-white/60" title="{{ auth()->user()->name }}">
            {{ auth()->user()->name }}
            @if (auth()->user()->role)
                <span class="text-white/40">· {{ auth()->user()->role->name }}</span>
            @endif
        </p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-white/80 transition-colors hover:bg-white/10 hover:text-white">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Mag-logout
            </button>
        </form>
    </div>
</aside>
