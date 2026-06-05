@php $isEdit = isset($user); @endphp

<div>
    <label for="name" class="form-label">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required class="form-input">
    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="email" class="form-label">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" required class="form-input">
    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="role_id" class="form-label">Role</label>
    <select id="role_id" name="role_id" required class="form-input">
        <option value="">Select a role</option>
        @foreach ($roles as $role)
            <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id ?? '') == $role->id)>{{ $role->name }}</option>
        @endforeach
    </select>
    @error('role_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="password" class="form-label">Password {{ $isEdit ? '(optional)' : '' }}</label>
    <input id="password" name="password" type="password" {{ $isEdit ? '' : 'required' }} class="form-input">
    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="password_confirmation" class="form-label">Confirm Password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" class="form-input">
</div>
