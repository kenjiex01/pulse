<div data-live-table-total-update data-total="{{ $users->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Roles</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $user->name }}</td>
                        <td class="text-gray-600">{{ $user->email }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1.5">
                                @forelse ($user->roles as $role)
                                    <span class="badge-brand">{{ $role->name }}</span>
                                @empty
                                    <span class="badge-muted">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @can('view', $user)
                                    <button type="button" data-modal-open="user-view-modal-{{ $user->id }}" class="btn-icon" title="View">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                @endcan
                                @can('update', $user)
                                    <button type="button" data-modal-open="user-edit-modal-{{ $user->id }}" class="btn-icon" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-12 text-center">
                            <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <p class="text-sm font-medium text-gray-500">No users found</p>
                            <p class="mt-1 text-xs text-gray-400">Click "New User" to get started.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="datatable-skolaris-pagination mt-4">
    @include('partials.data-table-pagination', ['paginator' => $users])
</div>

<div data-live-table-modals>
    @foreach ($users as $user)
        @can('view', $user)
            @include('partials.modal', [
                'id' => 'user-view-modal-'.$user->id,
                'title' => $user->name,
                'description' => 'User account details',
                'open' => (string) ($openViewUserId ?? '') === (string) $user->id,
                'body' => view('users._show-content', compact('user'))->render(),
            ])
        @endcan
        @can('update', $user)
            @include('partials.modal', [
                'id' => 'user-edit-modal-'.$user->id,
                'title' => 'Edit User',
                'description' => 'Update user information',
                'open' => (string) ($openEditUserId ?? '') === (string) $user->id,
                'body' => view('users._edit-form', ['user' => $user, 'roles' => $roles])->render(),
            ])
        @endcan
    @endforeach
</div>
