@php
    use App\Support\HolidaySettings as HolidaySettingsSupport;
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route(HolidaySettingsSupport::routeName('update-holiday'), $record->timekeeping_holiday_id) : route(HolidaySettingsSupport::routeName('store-holiday')) }}"
    class="space-y-4"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="edit_record_id" value="{{ $record->timekeeping_holiday_id }}">
    @endif
    <input type="hidden" name="form_context" value="{{ $formContext }}">

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Holiday Code <span class="text-red-500">*</span></label>
            <input type="text" name="timekeeping_holiday_code" maxlength="4" value="{{ old('timekeeping_holiday_code', $record?->timekeeping_holiday_code) }}" class="form-input uppercase" required>
            @error('timekeeping_holiday_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="recurring" value="0">
                <input type="checkbox" name="recurring" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(filter_var(old('recurring', $record?->recurring ?? false), FILTER_VALIDATE_BOOLEAN))>
                Recurring
            </label>
        </div>
        <div class="sm:col-span-2">
            <label class="form-label">Description <span class="text-red-500">*</span></label>
            <input type="text" name="description" maxlength="75" value="{{ old('description', $record?->description) }}" class="form-input" required>
            @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="form-label">Short Description</label>
            <input type="text" name="short_description" maxlength="25" value="{{ old('short_description', $record?->short_description) }}" class="form-input">
            @error('short_description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Date <span class="text-red-500">*</span></label>
            <input type="date" name="dt_datestamp" value="{{ old('dt_datestamp', $record?->dt_datestamp?->format('Y-m-d')) }}" class="form-input" required>
            @error('dt_datestamp')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Legal / Special <span class="text-red-500">*</span></label>
            <div class="flex gap-4 pt-2 text-sm text-gray-700">
                <label class="flex items-center gap-2">
                    <input type="radio" name="is_legal" value="1" @checked(filter_var(old('is_legal', $record?->is_legal ?? true), FILTER_VALIDATE_BOOLEAN)) required>
                    Legal
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="is_legal" value="0" @checked(! filter_var(old('is_legal', $record?->is_legal ?? true), FILTER_VALIDATE_BOOLEAN))>
                    Special
                </label>
            </div>
            @error('is_legal')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => $isEdit ? 'Save Changes' : 'Create Holiday',
    ])
</form>
