@php
    use App\Support\HolidaySettings as HolidaySettingsSupport;

    $selectedIds = collect(old('holiday_ids', $record?->holidays?->pluck('timekeeping_holiday_id')->all() ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route(HolidaySettingsSupport::routeName('update-group'), $record->timekeeping_holiday_group_id) : route(HolidaySettingsSupport::routeName('store-group')) }}"
    class="space-y-4"
    data-holiday-group-form
>
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="edit_record_id" value="{{ $record->timekeeping_holiday_group_id }}">
    @endif
    <input type="hidden" name="form_context" value="{{ $formContext }}">

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Holiday Group Code <span class="text-red-500">*</span></label>
            <input type="text" name="timekeeping_holiday_group_code" maxlength="4" value="{{ old('timekeeping_holiday_group_code', $record?->timekeeping_holiday_group_code) }}" class="form-input uppercase" required>
            @error('timekeeping_holiday_group_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="form-label">Description <span class="text-red-500">*</span></label>
            <input type="text" name="description" maxlength="75" value="{{ old('description', $record?->description) }}" class="form-input" required>
            @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        @include('partials.dual-list-select', [
            'name' => 'holiday_ids[]',
            'label' => 'Select Holidays',
            'options' => $holidayOptions,
            'selected' => $selectedIds,
            'required' => true,
            'hint' => 'Select items from the list, then use >> / << to move them (paths-mvc style).',
            'size' => 10,
        ])
        @error('holiday_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('holiday_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => $isEdit ? 'Save Changes' : 'Create Group',
    ])
</form>
