@php
    $isEdit = isset($user);
    $fieldIdPrefix = $fieldIdPrefix ?? '';
    $selectedRoleIds = collect(old('role_ids'));

    if ($selectedRoleIds->isEmpty() && isset($user)) {
        $selectedRoleIds = $user->roles->pluck('id');
    }
@endphp

<div>
    <label for="{{ $fieldIdPrefix }}name" class="form-label">Name</label>
    <input id="{{ $fieldIdPrefix }}name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required class="form-input">
    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="{{ $fieldIdPrefix }}email" class="form-label">Email</label>
    <input id="{{ $fieldIdPrefix }}email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" required class="form-input">
    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <p class="form-label">Roles</p>
    <p class="mb-3 text-xs text-gray-500">Select one or more roles for this user. Module access is combined from all assigned roles.</p>
    <div class="space-y-2 rounded-lg border border-gray-200 p-3">
        @foreach ($roles as $role)
            <label class="flex cursor-pointer items-start gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-gray-50">
                <input
                    type="checkbox"
                    name="role_ids[]"
                    value="{{ $role->id }}"
                    class="mt-0.5 rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                    @checked($selectedRoleIds->contains($role->id))
                >
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-gray-900">{{ $role->name }}</span>
                    <span class="block text-xs text-gray-500">{{ $role->slug }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error('role_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="{{ $fieldIdPrefix }}password" class="form-label">Password {{ $isEdit ? '(optional)' : '' }}</label>
    <input id="{{ $fieldIdPrefix }}password" name="password" type="password" {{ $isEdit ? '' : 'required' }} class="form-input">
    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="{{ $fieldIdPrefix }}password_confirmation" class="form-label">Confirm Password</label>
    <input id="{{ $fieldIdPrefix }}password_confirmation" name="password_confirmation" type="password" class="form-input">
</div>
