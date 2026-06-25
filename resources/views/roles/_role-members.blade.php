@php
    $membersRootId = isset($role) ? 'role-members-'.$role->id : 'role-members-create';
    $selectedMemberIds = collect(old('member_ids'));

    if ($selectedMemberIds->isEmpty() && isset($role)) {
        $selectedMemberIds = $role->users->pluck('id');
    }

    $allUsersCollection = collect($allUsers);
    $members = isset($role)
        ? $role->users
        : $allUsersCollection
            ->filter(fn ($user) => $selectedMemberIds->contains(is_array($user) ? $user['id'] : $user->id))
            ->map(fn ($user) => is_array($user) ? (object) $user : $user);

    $usersPayload = $allUsersCollection->map(function ($user) {
        if (is_array($user)) {
            return $user;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_ids' => $user->roles->pluck('id')->values()->all(),
            'role_names' => $user->roles->pluck('name')->values()->all(),
        ];
    })->values();
@endphp

<div
    class="space-y-3"
    data-role-members-root
    data-role-members-id="{{ $membersRootId }}"
    data-role-id="{{ $role->id ?? '' }}"
    data-users='@json($usersPayload)'
>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="form-label">Role Members</p>
            <p class="text-xs text-gray-500">Users assigned to this role. A user can belong to multiple roles.</p>
        </div>
        <button
            type="button"
            class="btn-secondary w-full sm:w-auto"
            data-role-members-add-open="{{ $membersRootId }}-add-modal"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Members
        </button>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200">
        <table class="table-skolaris">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody data-role-members-table>
                @forelse ($members as $member)
                    <tr data-role-member-row="{{ $member->id }}">
                        <td class="font-medium text-gray-900">{{ $member->name }}</td>
                        <td class="text-gray-600">{{ $member->email }}</td>
                        <td class="text-right">
                            <button type="button" class="btn-icon text-red-500 hover:bg-red-50 hover:text-red-600" data-role-member-remove="{{ $member->id }}" title="Remove">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr data-role-members-empty>
                        <td colspan="3" class="py-8 text-center text-sm text-gray-500">No members added yet. Click "Add Members" to assign users.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div data-role-members-inputs>
        @foreach ($members as $member)
            <input type="hidden" name="member_ids[]" value="{{ $member->id }}" data-member-id="{{ $member->id }}">
        @endforeach
    </div>

    @error('member_ids')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
</div>
