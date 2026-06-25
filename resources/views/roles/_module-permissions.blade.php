@php
    $selectedModules = old('modules');
    $selectedSubModules = old('sub_modules');

    if ($selectedModules === null && isset($role)) {
        $selectedModules = $role->modules->mapWithKeys(function ($module) {
            return [
                $module->id => [
                    'can_add' => (bool) $module->pivot->can_add,
                    'can_edit' => (bool) $module->pivot->can_edit,
                    'can_update' => (bool) $module->pivot->can_update,
                    'can_delete' => (bool) $module->pivot->can_delete,
                    'full_control' => (bool) $module->pivot->full_control,
                ],
            ];
        })->all();
    }

    if ($selectedSubModules === null && isset($role)) {
        $selectedSubModules = $role->subModules->mapWithKeys(function ($subModule) {
            return [
                $subModule->id => [
                    'can_add' => (bool) $subModule->pivot->can_add,
                    'can_edit' => (bool) $subModule->pivot->can_edit,
                    'can_update' => (bool) $subModule->pivot->can_update,
                    'can_delete' => (bool) $subModule->pivot->can_delete,
                    'full_control' => (bool) $subModule->pivot->full_control,
                ],
            ];
        })->all();
    }

    $selectedModules = $selectedModules ?? [];
    $selectedSubModules = $selectedSubModules ?? [];
@endphp

<div>
    <p class="form-label">Module Access</p>
    <p class="mb-3 text-xs text-gray-500">Choose which modules this role can access and what actions are allowed.</p>

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
                            @php
                                $subModulePermissions = $selectedSubModules[$subModule->id] ?? [];
                                $fullControl = filter_var($subModulePermissions['full_control'] ?? false, FILTER_VALIDATE_BOOLEAN);
                            @endphp
                            <tr class="permission-sub-row" data-permission-row="{{ $subModule->id }}">
                                <td class="pl-8 text-gray-700">
                                    <span class="text-gray-400">↳</span> {{ $subModule->name }}
                                </td>
                                @foreach (['can_add', 'can_edit', 'can_update', 'can_delete'] as $permissionKey)
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            name="sub_modules[{{ $subModule->id }}][{{ $permissionKey }}]"
                                            value="1"
                                            class="permission-checkbox"
                                            data-permission-sub-module="{{ $subModule->id }}"
                                            data-permission-type="{{ $permissionKey }}"
                                            @checked(filter_var($subModulePermissions[$permissionKey] ?? false, FILTER_VALIDATE_BOOLEAN))
                                            @disabled($fullControl)
                                        >
                                    </td>
                                @endforeach
                                <td class="text-center">
                                    <input
                                        type="checkbox"
                                        name="sub_modules[{{ $subModule->id }}][full_control]"
                                        value="1"
                                        class="permission-full-control"
                                        data-permission-sub-module="{{ $subModule->id }}"
                                        @checked($fullControl)
                                    >
                                </td>
                            </tr>
                        @endforeach
                    @else
                        @php
                            $modulePermissions = $selectedModules[$module->id] ?? [];
                            $fullControl = filter_var($modulePermissions['full_control'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        @endphp
                        <tr data-permission-row="{{ $module->id }}">
                            <td class="font-medium text-gray-900">{{ $module->name }}</td>
                            @foreach (['can_add', 'can_edit', 'can_update', 'can_delete'] as $permissionKey)
                                <td class="text-center">
                                    <input
                                        type="checkbox"
                                        name="modules[{{ $module->id }}][{{ $permissionKey }}]"
                                        value="1"
                                        class="permission-checkbox"
                                        data-permission-module="{{ $module->id }}"
                                        data-permission-type="{{ $permissionKey }}"
                                        @checked(filter_var($modulePermissions[$permissionKey] ?? false, FILTER_VALIDATE_BOOLEAN))
                                        @disabled($fullControl)
                                    >
                                </td>
                            @endforeach
                            <td class="text-center">
                                <input
                                    type="checkbox"
                                    name="modules[{{ $module->id }}][full_control]"
                                    value="1"
                                    class="permission-full-control"
                                    data-permission-module="{{ $module->id }}"
                                    @checked($fullControl)
                                >
                            </td>
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
    @error('modules')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    @error('sub_modules')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
