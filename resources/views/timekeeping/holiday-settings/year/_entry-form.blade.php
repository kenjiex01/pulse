@php
    use App\Support\HolidaySettings as HolidaySettingsSupport;
@endphp

<form method="POST" action="{{ route(HolidaySettingsSupport::routeName('update-year-entry'), [$year->timekeeping_year_id, $entry->timekeeping_holiday_year_id]) }}" class="space-y-4">
    @csrf
    @method('PUT')
    <input type="hidden" name="edit_year_id" value="{{ $year->timekeeping_year_id }}">

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Holiday Code <span class="text-red-500">*</span></label>
            <input type="text" name="timekeeping_holiday_code" maxlength="4" value="{{ old('timekeeping_holiday_code', $entry->timekeeping_holiday_code) }}" class="form-input uppercase" required>
            @error('timekeeping_holiday_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Date <span class="text-red-500">*</span></label>
            <input type="date" name="dt_datestamp" value="{{ old('dt_datestamp', $entry->dt_datestamp?->format('Y-m-d')) }}" class="form-input" required>
            @error('dt_datestamp')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Legal / Special <span class="text-red-500">*</span></label>
            <div class="flex gap-4 pt-2 text-sm text-gray-700">
                <label class="flex items-center gap-2">
                    <input type="radio" name="is_legal" value="1" @checked(filter_var(old('is_legal', $entry->is_legal), FILTER_VALIDATE_BOOLEAN)) required>
                    Legal
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="is_legal" value="0" @checked(! filter_var(old('is_legal', $entry->is_legal), FILTER_VALIDATE_BOOLEAN))>
                    Special
                </label>
            </div>
            @error('is_legal')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="recurring" value="0">
                <input type="checkbox" name="recurring" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(filter_var(old('recurring', $entry->recurring), FILTER_VALIDATE_BOOLEAN))>
                Recurring
            </label>
        </div>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Save Changes',
    ])
</form>
