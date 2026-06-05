<div class="space-y-4">
    <div>
        <p class="text-sm text-gray-600">Name</p>
        <p class="mt-1 font-medium text-gray-900">{{ $role->name }}</p>
    </div>
    <div>
        <p class="text-sm text-gray-600">Slug</p>
        <p class="mt-1"><span class="badge-brand">{{ $role->slug }}</span></p>
    </div>
    <div>
        <p class="text-sm text-gray-600">Description</p>
        <p class="mt-1 font-medium text-gray-900">{{ $role->description ?? '—' }}</p>
    </div>
    <div>
        <p class="text-sm text-gray-600">User count</p>
        <p class="mt-1 font-medium text-gray-900">{{ $role->users_count }}</p>
    </div>

    <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
        <button type="button" class="btn-secondary w-full sm:w-auto" data-modal-close>Close</button>
        @can('update', $role)
            <button type="button" class="btn-secondary w-full sm:w-auto" data-modal-open="role-edit-modal-{{ $role->id }}">Edit</button>
        @endcan
        @can('delete', $role)
            <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Are you sure you want to delete this role?')" class="w-full sm:w-auto">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger w-full">Delete</button>
            </form>
        @endcan
    </div>
</div>
