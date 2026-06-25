<div class="space-y-4">
    <div>
        <p class="text-sm text-gray-600">Name</p>
        <p class="mt-1 font-medium text-gray-900">{{ $user->name }}</p>
    </div>
    <div>
        <p class="text-sm text-gray-600">Email</p>
        <p class="mt-1 font-medium text-gray-900">{{ $user->email }}</p>
    </div>
    <div>
        <p class="text-sm text-gray-600">Roles</p>
        <div class="mt-1 flex flex-wrap gap-1.5">
            @forelse ($user->roles as $role)
                <span class="badge-brand">{{ $role->name }}</span>
            @empty
                <span class="text-gray-500">—</span>
            @endforelse
        </div>
    </div>
    <div>
        <p class="text-sm text-gray-600">Created at</p>
        <p class="mt-1 font-medium text-gray-900">{{ $user->created_at?->format('M d, Y h:i A') }}</p>
    </div>

    <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
        <button type="button" class="btn-secondary w-full sm:w-auto" data-modal-close>Close</button>
        @can('update', $user)
            <button type="button" class="btn-secondary w-full sm:w-auto" data-modal-open="user-edit-modal-{{ $user->id }}">Edit</button>
        @endcan
        @can('delete', $user)
            <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to delete this user?')" class="w-full sm:w-auto">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger w-full">Delete</button>
            </form>
        @endcan
    </div>
</div>
