@extends('layouts.app')

@section('title', 'Users — '.config('app.name'))

@section('content')
    @php
        $openCreateUser = ($errors->any() && old('form_context') === 'create-user') || request()->boolean('create');
    @endphp

    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'User Management',
        'description' => 'Manage user accounts',
        'actionModalId' => 'user-create-modal',
        'actionLabel' => 'New User',
        'actionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    ])

    @include('partials.live-data-table', [
        'url' => route('users.index'),
        'search' => $search,
        'searchPlaceholder' => 'Search name or email...',
        'searchId' => 'users-search',
        'paginator' => $users,
        'totalLabel' => 'users',
        'results' => view('users._results', [
            'users' => $users,
            'roles' => $roles,
            'openViewUserId' => request('view_user'),
            'openEditUserId' => old('edit_user_id', request('edit_user')),
        ])->render(),
    ])

    @include('partials.modal', [
        'id' => 'user-create-modal',
        'title' => 'New User',
        'description' => 'Create a new user account',
        'open' => $openCreateUser,
        'body' => view('users._create-form', compact('roles'))->render(),
    ])
@endsection
