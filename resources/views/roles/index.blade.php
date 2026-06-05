@extends('layouts.app')

@section('title', 'Roles — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Role Management',
        'description' => 'Manage roles and access levels',
        'actionUrl' => route('roles.create'),
        'actionLabel' => 'New Role',
        'actionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    ])

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="table-skolaris">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Users</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $role->name }}</td>
                        <td><span class="badge-brand">{{ $role->slug }}</span></td>
                        <td>
                            <span class="font-semibold text-gray-800">{{ $role->users_count }}</span>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('roles.show', $role) }}" class="btn-icon" title="View">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="{{ route('roles.edit', $role) }}" class="btn-icon" title="Edit">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-12 text-center">
                            <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <p class="text-sm font-medium text-gray-500">No roles found</p>
                            <p class="mt-1 text-xs text-gray-400">Click "New Role" to get started.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($roles->hasPages())
        <div class="mt-4">
            {{ $roles->links() }}
        </div>
    @endif
@endsection
