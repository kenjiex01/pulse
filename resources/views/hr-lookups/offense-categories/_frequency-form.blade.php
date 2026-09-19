@php
    $primaryKey = $config['primary_key'];
    $isEdit = $record !== null;
    $action = $isEdit
        ? route(\App\Support\HrLookup::routeName('offense-frequencies', 'update'), $record->{$primaryKey})
        : route(\App\Support\HrLookup::routeName('offense-frequencies', 'store'));
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="form_context" value="{{ $formContext }}">
    @if ($isEdit)
        <input type="hidden" name="edit_record_id" value="{{ $record->{$primaryKey} }}">
        <input type="hidden" name="frequency_ordinal" value="{{ $record->frequency_ordinal }}">
    @endif

    @if ($isEdit)
        <div>
            <label class="form-label">Ordinal</label>
            <p class="text-sm text-gray-700">{{ $record->frequency_ordinal }}</p>
        </div>
    @else
        <p class="text-xs text-gray-500">A new offense frequency row will be added after the current last offense (e.g. Seventh Offense).</p>
    @endif

    <div>
        <label for="frequency_label_{{ $formContext }}" class="form-label">Label</label>
        <input
            id="frequency_label_{{ $formContext }}"
            name="label"
            type="text"
            value="{{ old('label', $record?->label) }}"
            class="form-input"
            placeholder="e.g. Seventh Offense"
            required
        >
        @error('label')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="frequency_sort_{{ $formContext }}" class="form-label">Sort Order</label>
        <input
            id="frequency_sort_{{ $formContext }}"
            name="sort_order"
            type="number"
            value="{{ old('sort_order', $record?->sort_order) }}"
            class="form-input"
            min="0"
        >
        @error('sort_order')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(filter_var(old('is_active', $record?->is_active ?? true), FILTER_VALIDATE_BOOLEAN))>
        <span class="text-sm font-medium text-gray-900">Active</span>
    </label>
    @error('is_active')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Save Changes' : 'Create' }}</button>
    </div>
</form>
