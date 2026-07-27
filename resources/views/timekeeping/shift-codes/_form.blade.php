@php
    use App\Support\ShiftCode as ShiftCodeSupport;

    $breakRows = old('breaks', ShiftCodeSupport::breakRowsForForm($record ?? null));
    $flexiEnabled = filter_var(old('is_flexi_time', $record?->is_flexi_time ?? false), FILTER_VALIDATE_BOOLEAN);
    $expectedHoursValue = old('expected_hours_per_day', $record?->expected_hours_per_day ?? 8);
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route(ShiftCodeSupport::routeName('update'), $record->shift_code_id) : route(ShiftCodeSupport::routeName('store')) }}"
    class="space-y-4"
    data-shift-code-form
>
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="edit_shift_code_id" value="{{ $record->shift_code_id }}">
    @endif
    <input type="hidden" name="form_context" value="{{ $formContext }}">

    <p class="text-sm text-gray-500">
        Please fill out all required fields. Time must be in 24-hour format (hh:mm).
    </p>

    <div class="rounded-lg border border-blue-100 bg-blue-50/60 p-4">
        <label class="flex items-start gap-2 text-sm text-gray-800">
            <input type="hidden" name="is_flexi_time" value="0">
            <input
                type="checkbox"
                name="is_flexi_time"
                value="1"
                class="mt-0.5 rounded border-gray-300 text-[#0B318F] focus:ring-[#0B318F]"
                data-flexi-shift-toggle
                @checked($flexiEnabled)
            >
            <span>
                <span class="font-medium">Flexi-time Shift</span>
                <span class="mt-1 block text-xs text-gray-600">
                    Employee may arrive anytime. Pay is based on actual rendered hours (first IN to last OUT, minus breaks).
                    No late or undertime. Overtime applies when rendered hours exceed the expected hours per day.
                </span>
            </span>
        </label>
        @error('is_flexi_time')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div data-flexi-expected-panel @class(['grid gap-4 sm:grid-cols-2' => true, 'hidden' => ! $flexiEnabled])>
        <div>
            <label for="expected_hours_per_day_{{ $formContext }}" class="form-label">Expected Hours Per Day <span class="text-red-500">*</span></label>
            <input
                id="expected_hours_per_day_{{ $formContext }}"
                name="expected_hours_per_day"
                type="number"
                step="0.25"
                min="0.25"
                max="24"
                value="{{ $expectedHoursValue }}"
                class="form-input max-w-[10rem]"
                data-flexi-expected-field
                @disabled(! $flexiEnabled)
            >
            <p class="mt-1 text-xs text-gray-500">Hal. 8 — basic pay caps here; excess becomes overtime.</p>
            @error('expected_hours_per_day')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2" data-flexi-schedule-panel>
        <div>
            <label for="shift_code_{{ $formContext }}" class="form-label">Shift Code <span class="text-red-500">*</span></label>
            <input
                id="shift_code_{{ $formContext }}"
                name="shift_code"
                type="text"
                maxlength="4"
                value="{{ old('shift_code', $record?->shift_code) }}"
                class="form-input uppercase"
                required
            >
            @error('shift_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label for="description_{{ $formContext }}" class="form-label">Description <span class="text-red-500">*</span></label>
            <input
                id="description_{{ $formContext }}"
                name="description"
                type="text"
                maxlength="45"
                value="{{ old('description', $record?->description) }}"
                class="form-input"
                required
            >
            @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="time_in_{{ $formContext }}" class="form-label">Break In</label>
            <input
                id="time_in_{{ $formContext }}"
                name="time_in"
                type="text"
                maxlength="5"
                placeholder="06:00"
                value="{{ old('time_in', $record?->time_in) }}"
                class="form-input"
            >
            @error('time_in')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="time_out_{{ $formContext }}" class="form-label">Break Out</label>
            <input
                id="time_out_{{ $formContext }}"
                name="time_out"
                type="text"
                maxlength="5"
                placeholder="15:00"
                value="{{ old('time_out', $record?->time_out) }}"
                class="form-input"
            >
            @error('time_out')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-gray-900">Breaks</h3>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary text-xs" data-shift-break-add>Add Break</button>
                <button type="button" class="btn-secondary text-xs" data-shift-break-remove>Remove</button>
            </div>
        </div>

        <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-[480px]">
                <thead>
                    <tr>
                        <th>Break No</th>
                        <th>Break Minute <span class="text-red-500">*</span></th>
                        <th>Paid Break?</th>
                    </tr>
                </thead>
                <tbody data-shift-break-rows>
                    @forelse ($breakRows as $index => $break)
                        <tr data-shift-break-row>
                            <td class="font-medium text-gray-700">Break {{ $index + 1 }}</td>
                            <td>
                                <input
                                    type="number"
                                    name="breaks[{{ $index }}][break_minute]"
                                    min="1"
                                    max="999"
                                    value="{{ $break['break_minute'] ?? '' }}"
                                    class="form-input max-w-[8rem]"
                                >
                                @error('breaks.'.$index.'.break_minute')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </td>
                            <td>
                                <input type="hidden" name="breaks[{{ $index }}][is_paid_break]" value="0">
                                <input
                                    type="checkbox"
                                    name="breaks[{{ $index }}][is_paid_break]"
                                    value="1"
                                    class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                                    @checked(filter_var($break['is_paid_break'] ?? false, FILTER_VALIDATE_BOOLEAN))
                                >
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>

        <template data-shift-break-template>
            <tr data-shift-break-row>
                <td class="font-medium text-gray-700" data-shift-break-label>Break 1</td>
                <td>
                    <input type="number" name="breaks[__INDEX__][break_minute]" min="1" max="999" class="form-input max-w-[8rem]">
                </td>
                <td>
                    <input type="hidden" name="breaks[__INDEX__][is_paid_break]" value="0">
                    <input type="checkbox" name="breaks[__INDEX__][is_paid_break]" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]">
                </td>
            </tr>
        </template>
    </div>

    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Save Changes' : 'Create' }}</button>
    </div>
</form>
