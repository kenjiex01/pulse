@extends('layouts.app')

@section('title', 'Roles — '.config('app.name'))

@section('content')
    @php
        $openCreateRole = ($errors->any() && old('form_context') === 'create-role') || request()->boolean('create');
    @endphp

    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Role Management',
        'description' => 'Manage roles and access levels',
        'actionModalId' => 'role-create-modal',
        'actionLabel' => 'New Role',
        'actionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    ])

    @include('partials.live-data-table', [
        'url' => route('roles.index'),
        'search' => $search,
        'searchPlaceholder' => 'Search role name or slug...',
        'searchId' => 'roles-search',
        'paginator' => $roles,
        'totalLabel' => 'roles',
        'results' => view('roles._results', [
            'roles' => $roles,
            'modules' => $modules,
            'allUsers' => $allUsers,
            'openViewRoleId' => request('view_role'),
            'openEditRoleId' => old('edit_role_id', request('edit_role')),
        ])->render(),
    ])

    @include('partials.modal', [
        'id' => 'role-create-modal',
        'title' => 'New Role',
        'description' => 'Create a new access role',
        'open' => $openCreateRole,
        'panelClass' => 'modal-panel-lg',
        'body' => view('roles._create-form', compact('modules', 'allUsers'))->render(),
    ])

    @include('roles._role-members-add-modal', ['membersRootId' => 'role-members-create'])
@endsection
