<div id="sidebar-overlay" class="fixed inset-0 z-[55] hidden bg-black/50 lg:hidden" aria-hidden="true"></div>

@php
    $isDesktopApp = (bool) config('nativephp-internal.running', env('NATIVEPHP_RUNNING', false));
    $desktopAppVersion = (string) config('nativephp.version', '0.0.0');
@endphp

<aside
    id="app-sidebar"
    class="fixed inset-y-0 left-0 z-[60] flex w-72 flex-col bg-[#0B318F] text-white shadow-xl transition-transform duration-300 ease-in-out"
>
    <div class="flex h-14 shrink-0 items-center justify-between border-b border-white/10 px-4">
        <div class="flex min-w-0 items-center gap-2">
            <img src="{{ asset('img/skolarislogo.png') }}" alt="Skolaris" class="h-8 w-auto shrink-0 brightness-0 invert">
            <div class="min-w-0">
                <p class="flex items-center gap-1 truncate text-sm font-bold">
                    <svg class="h-3.5 w-3.5 shrink-0 text-[#00A3E6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    People360
                </p>
                <p class="flex items-center gap-1.5 truncate text-[10px] text-white/60">
                    <span class="truncate">ISKOLARIS Desktop</span>
                    @if ($isDesktopApp)
                        <span class="shrink-0 font-medium text-white/75">v{{ $desktopAppVersion }}</span>
                    @endif
                </p>
            </div>
        </div>
        <button id="sidebar-close" type="button" class="shrink-0 rounded-lg p-1 text-white hover:bg-white/10" aria-label="Close sidebar">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="shrink-0 border-b border-white/10 px-3 py-3">
        <form role="search" onsubmit="return false;">
            <label for="sidebar-search" class="sr-only">Search modules</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input
                    id="sidebar-search"
                    type="search"
                    placeholder="Search modules..."
                    autocomplete="off"
                    class="sidebar-search"
                >
            </div>
        </form>
    </div>

    <nav class="flex-1 overflow-y-auto px-2 py-3">
        @forelse ($sidebarModules as $section => $modules)
            @php
                $hideSectionLabel = $modules->every(function ($module) {
                    $activeSubModules = $module->subModules->filter(fn ($subModule) => Route::has($subModule->route_name));

                    return $activeSubModules->isNotEmpty() && blank($module->route_name);
                });
            @endphp
            <div class="sidebar-section" data-sidebar-section>
                @unless ($hideSectionLabel)
                    <p class="sidebar-section-label mb-2 px-3 text-[10px] font-semibold uppercase tracking-wider text-white/40">{{ $section }}</p>
                @endunless
                <ul class="space-y-0.5">
                    @foreach ($modules as $module)
                        @php
                            $activeSubModules = $module->subModules->filter(fn ($subModule) => Route::has($subModule->route_name));
                            $hasSubModules = $activeSubModules->isNotEmpty();
                            $moduleActive = $module->route_pattern && request()->routeIs($module->route_pattern);
                            $subModuleActive = $activeSubModules->contains(fn ($subModule) => $subModule->route_pattern && request()->routeIs($subModule->route_pattern));
                        @endphp

                        @if ($hasSubModules)
                            <li>
                                <details
                                    class="sidebar-group"
                                    data-sidebar-group
                                    data-sidebar-item="{{ $module->name }}"
                                    @if ($subModuleActive) open @endif
                                >
                                    <summary class="sidebar-link cursor-pointer {{ $subModuleActive ? 'sidebar-link-active' : '' }}">
                                        @include('partials.sidebar-icon', ['icon' => $module->icon])
                                        <span class="min-w-0 flex-1 truncate text-left">{{ $module->name }}</span>
                                        <svg class="sidebar-group-chevron ml-auto h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    </summary>
                                    <div class="sidebar-sub-panel">
                                        <ul class="sidebar-sub-list mt-0.5 space-y-0.5 pl-3" data-sidebar-sub-list>
                                        @foreach ($activeSubModules as $subModule)
                                            @php $subActive = $subModule->route_pattern && request()->routeIs($subModule->route_pattern); @endphp
                                            <li data-sidebar-item="{{ $subModule->name }}">
                                                <a
                                                    href="{{ route($subModule->route_name) }}"
                                                    data-sidebar-close
                                                    class="sidebar-sub-link {{ $subActive ? 'sidebar-sub-link-active' : '' }}"
                                                >
                                                    @include('partials.sidebar-icon', ['icon' => $subModule->icon])
                                                    {{ $subModule->name }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                    </div>
                                </details>
                            </li>
                        @elseif ($module->route_name && Route::has($module->route_name))
                            <li data-sidebar-item="{{ $module->name }}">
                                <a
                                    href="{{ route($module->route_name) }}"
                                    data-sidebar-close
                                    class="sidebar-link {{ $moduleActive ? 'sidebar-link-active' : '' }}"
                                >
                                    @include('partials.sidebar-icon', ['icon' => $module->icon])
                                    {{ $module->name }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="px-3 text-xs text-white/50">No modules available.</p>
        @endforelse
        <p id="sidebar-search-empty" class="hidden px-3 py-4 text-xs text-white/50">No modules match your search.</p>
    </nav>

    <div class="shrink-0 space-y-2 border-t border-white/10 p-3">
        <p class="truncate px-2 text-xs text-white/60" title="{{ auth()->user()->name }}">
            {{ auth()->user()->name }}
            @if (auth()->user()->roles->isNotEmpty())
                <span class="text-white/40">· {{ auth()->user()->roleLabel() }}</span>
            @endif
        </p>
        <form id="logout-form" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-white/80 transition-colors hover:bg-white/10 hover:text-white">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Log out
            </button>
        </form>
    </div>
</aside>
