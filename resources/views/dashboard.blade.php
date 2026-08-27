@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name'))

@section('content')
    <div class="welcome-banner">
        <div class="absolute right-0 top-0 h-48 w-48 translate-x-1/4 -translate-y-1/2 rounded-full bg-white/5" aria-hidden="true"></div>
        <div class="relative">
            <div class="mb-2 inline-flex items-center gap-2 text-sm text-white/90">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                People360
            </div>
            <h1 class="mb-1 text-2xl font-bold sm:text-3xl">Welcome, {{ $user->name }}</h1>
            <p class="max-w-xl text-sm text-white/85 sm:text-base">
                ISKOLARIS desktop workspace — manage users, roles, and system access from one place.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-4 lg:gap-6">
        @include('partials.stat-card', [
            'index' => 0,
            'title' => 'Signed in as',
            'value' => $user->name,
            'subtitle' => $user->email,
            'icon' => '<svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
        ])
        @include('partials.stat-card', [
            'index' => 1,
            'title' => 'Roles',
            'value' => $user->roleLabel(),
            'subtitle' => $user->roles->pluck('slug')->join(', ') ?: 'No roles',
            'icon' => '<svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
        ])
        @if ($user->isAdmin())
            @include('partials.stat-card', [
                'index' => 2,
                'title' => 'Total Users',
                'value' => $userCount,
                'subtitle' => 'All accounts in the system',
                'icon' => '<svg class="h-5 w-5 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
            ])
            @include('partials.stat-card', [
                'index' => 3,
                'title' => 'Total Roles',
                'value' => $roleCount,
                'subtitle' => 'Configured access levels',
                'icon' => '<svg class="h-5 w-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
            ])
        @else
            @include('partials.stat-card', [
                'index' => 2,
                'title' => 'Status',
                'value' => 'Active',
                'subtitle' => 'Successfully signed in',
                'progress' => 100,
                'icon' => '<svg class="h-5 w-5 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            ])
            @include('partials.stat-card', [
                'index' => 3,
                'title' => 'Access',
                'value' => 'Limited',
                'subtitle' => 'Contact admin for more modules',
                'progress' => 60,
                'icon' => '<svg class="h-5 w-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>',
            ])
        @endif
    </div>

    @if ($user->isAdmin())
        <div>
            <h2 class="mb-4 text-lg font-semibold text-gray-900">Quick access</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <a href="{{ route('users.index') }}" class="group quick-link-card block no-underline">
                    <div class="mb-3 inline-flex rounded-xl bg-gradient-to-br from-[#0B318F] to-[#00A3E6] p-2.5 text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-[#0B318F] transition-colors group-hover:text-[#00A3E6]">User Management</h3>
                    <p class="mb-3 mt-1 text-xs text-gray-500">Create, edit, and manage user accounts.</p>
                    <span class="inline-flex items-center text-xs font-medium text-[#00A3E6]">
                        Open
                        <svg class="ml-1 h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </span>
                </a>
                <a href="{{ route('roles.index') }}" class="group quick-link-card block no-underline">
                    <div class="mb-3 inline-flex rounded-xl bg-gradient-to-br from-[#0077b6] to-[#00A3E6] p-2.5 text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="font-semibold text-[#0B318F] transition-colors group-hover:text-[#00A3E6]">Role Management</h3>
                    <p class="mb-3 mt-1 text-xs text-gray-500">Configure roles and permission levels.</p>
                    <span class="inline-flex items-center text-xs font-medium text-[#00A3E6]">
                        Open
                        <svg class="ml-1 h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </span>
                </a>
                @if ($databaseBackupPath)
                    <a href="{{ $databaseBackupPath }}" class="group quick-link-card block no-underline" data-no-loader>
                        <div class="mb-3 inline-flex rounded-xl bg-gradient-to-br from-[#0f766e] to-[#14b8a6] p-2.5 text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </div>
                        <h3 class="font-semibold text-[#0B318F] transition-colors group-hover:text-[#00A3E6]">Database</h3>
                        <p class="mb-3 mt-1 text-xs text-gray-500">Download a SQL backup of the current database.</p>
                        <span class="inline-flex items-center text-xs font-medium text-[#00A3E6]">
                            Open
                            <svg class="ml-1 h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    </a>
                @endif
            </div>
        </div>
    @endif
@endsection
