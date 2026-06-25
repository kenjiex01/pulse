@php
    $primaryKey = 'timekeeping_policy_id';
    $action = $isEdit
        ? route(\App\Support\TimekeepingPolicy::routeName('update-header'), $record->{$primaryKey})
        : route(\App\Support\TimekeepingPolicy::routeName('store'));
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <input type="hidden" name="form_context" value="{{ $formContext }}">
    @if ($isEdit)
        <input type="hidden" name="edit_record_id" value="{{ $record->{$primaryKey} }}">
    @endif

    <div>
        <label for="policy_code_{{ $formContext }}" class="form-label">Policy Code <span class="text-red-500">*</span></label>
        <input
            id="policy_code_{{ $formContext }}"
            name="policy_code"
            type="text"
            value="{{ old('policy_code', $record?->policy_code) }}"
            class="form-input uppercase"
            maxlength="30"
            required
            @disabled($isEdit)
        >
        @if ($isEdit)
            <input type="hidden" name="policy_code" value="{{ $record->policy_code }}">
            <p class="mt-1 text-xs text-gray-500">Policy code cannot be changed after creation.</p>
        @endif
        @error('policy_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="policy_name_{{ $formContext }}" class="form-label">Policy Name <span class="text-red-500">*</span></label>
        <input
            id="policy_name_{{ $formContext }}"
            name="policy_name"
            type="text"
            value="{{ old('policy_name', $record?->policy_name) }}"
            class="form-input"
            maxlength="100"
            required
        >
        @error('policy_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description_{{ $formContext }}" class="form-label">Description</label>
        <textarea id="description_{{ $formContext }}" name="description" rows="3" class="form-input min-h-[80px] py-2">{{ old('description', $record?->description) }}</textarea>
        @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-3">
        <input type="hidden" name="is_active" value="0">
        <input
            type="checkbox"
            name="is_active"
            value="1"
            class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
            @checked(filter_var(old('is_active', $record?->is_active ?? true), FILTER_VALIDATE_BOOLEAN))
        >
        <span class="text-sm font-medium text-gray-900">Active</span>
    </label>
    @error('is_active')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Save Changes' : 'Create Policy' }}</button>
    </div>
</form>
