@php
    $codeLocked = $isEdit && $record?->isInUse();
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route('payroll.rate-definitions.day-types.update', $record->day_type_id) : route('payroll.rate-definitions.day-types.store') }}"
    class="space-y-4"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="edit_record_id" value="{{ $record->day_type_id }}">
    @endif
    <input type="hidden" name="form_context" value="{{ $formContext }}">

    <div>
        <label for="day_type_code_{{ $formContext }}" class="form-label">Day Type Code</label>
        <input
            id="day_type_code_{{ $formContext }}"
            name="day_type_code"
            type="text"
            maxlength="4"
            value="{{ old('day_type_code', $record?->day_type_code) }}"
            class="form-input uppercase"
            @disabled($codeLocked)
            required
        >
        @if ($codeLocked)
            <p class="mt-1 text-xs text-gray-500">Code cannot be changed while this day type is used in rate groups.</p>
        @endif
        @error('day_type_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description_{{ $formContext }}" class="form-label">Description</label>
        <input id="description_{{ $formContext }}" name="description" type="text" maxlength="45" value="{{ old('description', $record?->description) }}" class="form-input" required>
        @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-3">
        <input type="hidden" name="is_restday" value="0">
        <input type="checkbox" name="is_restday" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(filter_var(old('is_restday', $record?->is_restday), FILTER_VALIDATE_BOOLEAN))>
        <span class="text-sm font-medium text-gray-900">Restday</span>
    </label>

    <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-3">
        <input type="hidden" name="is_special_holiday" value="0">
        <input type="checkbox" name="is_special_holiday" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(filter_var(old('is_special_holiday', $record?->is_special_holiday), FILTER_VALIDATE_BOOLEAN))>
        <span class="text-sm font-medium text-gray-900">Special Holiday</span>
    </label>

    <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-3">
        <input type="hidden" name="is_legal_holiday" value="0">
        <input type="checkbox" name="is_legal_holiday" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(filter_var(old('is_legal_holiday', $record?->is_legal_holiday), FILTER_VALIDATE_BOOLEAN))>
        <span class="text-sm font-medium text-gray-900">Legal Holiday</span>
    </label>

    <div>
        <label for="day_id_{{ $formContext }}" class="form-label">Day of the Week</label>
        <select id="day_id_{{ $formContext }}" name="day_id" class="form-input">
            <option value="">Any Day</option>
            @foreach ($selectOptions['days'] ?? [] as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) old('day_id', $record?->day_id ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
        @error('day_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">Save</button>
    </div>
</form>
