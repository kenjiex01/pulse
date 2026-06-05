@php $isEdit = isset($role); @endphp

<div>
    <label for="name" class="form-label">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $role->name ?? '') }}" required class="form-input">
    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

@if (! $isEdit)
    <div>
        <label for="slug" class="form-label">Slug</label>
        <input id="slug" name="slug" type="text" value="{{ old('slug', $role->slug ?? '') }}" required placeholder="e.g. encoder" class="form-input">
        <p class="mt-1 text-xs text-gray-500">Used for access control (e.g. admin, staff)</p>
        @error('slug')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
@else
    <div>
        <p class="form-label">Slug</p>
        <span class="badge-brand">{{ $role->slug }}</span>
    </div>
@endif

<div>
    <label for="description" class="form-label">Description</label>
    <textarea id="description" name="description" rows="3" class="form-input">{{ old('description', $role->description ?? '') }}</textarea>
    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
