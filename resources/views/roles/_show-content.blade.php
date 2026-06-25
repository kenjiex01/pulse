@php
    $assignedModules = $role->modules->keyBy('id');
    $assignedSubModules = $role->subModules->keyBy('id');
@endphp

<div class="space-y-5">
    <div>
        <p class="text-sm text-gray-600">Name</p>
        <p class="mt-1 font-medium text-gray-900">{{ $role->name }}</p>
    </div>
    <div>
        <p class="text-sm text-gray-600">Role</p>
        <p class="mt-1"><span class="badge-brand">{{ $role->slug }}</span></p>
    </div>
    <div>
        <p class="text-sm text-gray-600">Description</p>
        <p class="mt-1 font-medium text-gray-900">{{ $role->description ?? '—' }}</p>
    </div>

    <div>
        <p class="form-label">Module Access</p>
        <div class="overflow-hidden rounded-lg border border-gray-200">
            <table class="permission-table">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th class="text-center">Add</th>
                        <th class="text-center">Edit</th>
                        <th class="text-center">Update</th>
                        <th class="text-center">Delete</th>
                        <th class="text-center">Full Control</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($modules as $module)
                        @if ($module->subModules->isNotEmpty())
                            <tr class="permission-module-row">
                                <td class="font-semibold text-gray-900">{{ $module->name }}</td>
                                <td colspan="5"></td>
                            </tr>
                            @foreach ($module->subModules as $subModule)
                                @php $permissions = $assignedSubModules->get($subModule->id)?->pivot; @endphp
                                <tr class="permission-sub-row">
                                    <td class="pl-8 text-gray-600">
                                        <span class="text-gray-400">↳</span> {{ $subModule->name }}
                                    </td>
                                    @include('roles._module-permission-badges', ['permissions' => $permissions])
                                </tr>
                            @endforeach
                        @else
                            @php $permissions = $assignedModules->get($module->id)?->pivot; @endphp
                            <tr>
                                <td class="font-medium text-gray-900">{{ $module->name }}</td>
                                @include('roles._module-permission-badges', ['permissions' => $permissions])
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-sm text-gray-500">No active modules found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <p class="form-label">Role Members</p>
        <div class="overflow-hidden rounded-lg border border-gray-200">
            <table class="table-skolaris">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($role->users as $member)
                        <tr>
                            <td class="font-medium text-gray-900">{{ $member->name }}</td>
                            <td class="text-gray-600">{{ $member->email }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="py-6 text-center text-sm text-gray-500">No users assigned to this role.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
