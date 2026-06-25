@php
    use App\Support\TimeCaptureFormat as TimeCaptureFormatSupport;

    $mapping = TimeCaptureFormatSupport::mappingStateForForm($record ?? null);
    $sameColumnIndicator = filter_var(old('same_column_indicator', $mapping['same_column_indicator'] ?? false), FILTER_VALIDATE_BOOLEAN);

    $customFields = old('custom_fields', $mapping['custom_fields'] ?? []);

    if ($customFields === []) {
        $customFields = [['field_name' => '', 'column' => '', 'description' => '', 'timecapture_field_id' => '']];
    }
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route(TimeCaptureFormatSupport::routeName('update'), $record->timecapture_format_id) : route(TimeCaptureFormatSupport::routeName('store')) }}"
    class="space-y-5"
    data-time-capture-format-form
>
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="edit_timecapture_format_id" value="{{ $record->timecapture_format_id }}">
    @endif
    <input type="hidden" name="form_context" value="{{ $formContext }}">

    <p class="text-sm text-gray-500">
        Specify the column order of fields in the uploaded file. At least four mappings are required.
    </p>

    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
        <p class="font-medium">Biometric format (separate row per punch)</p>
        <p class="mt-1 text-blue-800">
            Each time in and time out is its own row. Use one shared time column plus an indicator column
            (<span class="font-mono">1</span> = Time In, <span class="font-mono">0</span> = Time Out).
        </p>
        <pre class="mt-3 overflow-x-auto rounded bg-white/80 p-3 font-mono text-xs text-gray-700">11    2026-06-01  09:00  1
11    2026-06-01  18:00  0</pre>
        <p class="mt-2 text-xs text-blue-700">
            Columns: Biometric ID | Date | Time | Indicator. Enable “Same column for Time In / Time Out” below.
        </p>
    </div>

    @error('mappings')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="device_name_{{ $formContext }}" class="form-label">Device Name <span class="text-red-500">*</span></label>
            <input
                id="device_name_{{ $formContext }}"
                name="device_name"
                type="text"
                maxlength="50"
                value="{{ old('device_name', $record?->device_name) }}"
                class="form-input uppercase"
                required
            >
            @error('device_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label for="format_description_{{ $formContext }}" class="form-label">Description <span class="text-red-500">*</span></label>
            <input
                id="format_description_{{ $formContext }}"
                name="description"
                type="text"
                maxlength="100"
                value="{{ old('description', $record?->description) }}"
                class="form-input"
                required
            >
            @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 p-4">
        <h3 class="mb-3 text-sm font-semibold text-gray-900">Standard Field Mappings</h3>

        <div class="space-y-3">
            <div>
                <label class="form-label">Employee ID <span class="text-red-500">*</span></label>
                <div class="flex gap-3">
                    <select name="employee_id_type" class="form-input min-w-0 flex-1" data-no-searchable-select>
                        @foreach (config('time_capturing_settings.employee_id_types') as $value => $label)
                            <option value="{{ $value }}" @selected(old('employee_id_type', $mapping['employee_id_type'] ?? 'employee_number') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="employee_id_column" min="1" max="99" value="{{ old('employee_id_column', $mapping['employee_id_column'] ?? '') }}" class="form-input w-24 shrink-0" aria-label="Employee ID column" placeholder="Col" required>
                </div>
                @error('employee_id_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @error('employee_id_column')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="form-label">Date <span class="text-red-500">*</span></label>
                <div class="flex gap-3">
                    <select name="date_type" class="form-input min-w-0 flex-1" data-no-searchable-select>
                        @foreach (config('time_capturing_settings.date_types') as $value => $label)
                            <option value="{{ $value }}" @selected(old('date_type', $mapping['date_type'] ?? 'actual_date') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="date_column" min="1" max="99" value="{{ old('date_column', $mapping['date_column'] ?? '') }}" class="form-input w-24 shrink-0" aria-label="Date column" placeholder="Col" required>
                </div>
                @error('date_column')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="reason_enabled" value="0">
                    <input type="checkbox" name="reason_enabled" value="1" data-tcf-reason-toggle class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(old('reason_enabled', $mapping['reason_enabled'] ?? false))>
                    Logout Reason
                </label>
                <input type="number" name="reason_column" min="1" max="99" value="{{ old('reason_column', $mapping['reason_column'] ?? '') }}" class="form-input ml-auto w-24 shrink-0" data-tcf-reason-column aria-label="Logout reason column" placeholder="Col">
                @error('reason_column')<p class="w-full text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div data-tcf-time-in-row @class(['opacity-50' => $sameColumnIndicator])>
                <label class="form-label">Time In @if (! $sameColumnIndicator)<span class="text-red-500">*</span>@endif</label>
                <div class="flex gap-3">
                    <select name="time_in_type" class="form-input min-w-0 flex-1" data-tcf-time-in-type data-no-searchable-select @disabled($sameColumnIndicator)>
                        <option value="" @selected($sameColumnIndicator || old('time_in_type', $mapping['time_in_type'] ?? 'time_in') === '')>—</option>
                        @foreach (config('time_capturing_settings.time_in_types') as $value => $label)
                            <option value="{{ $value }}" @selected(! $sameColumnIndicator && old('time_in_type', $mapping['time_in_type'] ?? 'time_in') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input
                        type="number"
                        name="time_in_column"
                        min="1"
                        max="99"
                        value="{{ old('time_in_column', $sameColumnIndicator ? '' : ($mapping['time_in_column'] ?? '')) }}"
                        class="form-input w-24 shrink-0"
                        data-tcf-time-in-column
                        aria-label="Time in column"
                        placeholder="Col"
                        @disabled($sameColumnIndicator)
                        @required(! $sameColumnIndicator)
                    >
                </div>
                @error('time_in_column')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap items-center gap-3" data-tcf-time-out-row>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="time_out_enabled" value="0">
                    <input type="checkbox" name="time_out_enabled" value="1" data-tcf-time-out-toggle class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(old('time_out_enabled', $mapping['time_out_enabled'] ?? true))>
                    Time Out
                </label>
                <input type="number" name="time_out_column" min="1" max="99" value="{{ old('time_out_column', $mapping['time_out_column'] ?? '') }}" class="form-input ml-auto w-24 shrink-0" data-tcf-time-out-column aria-label="Time out column" placeholder="Col">
                @error('time_out_column')<p class="w-full text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-md border border-dashed border-gray-200 p-3" data-tcf-indicator-section>
                <label class="flex items-center gap-2 text-sm font-medium text-gray-800">
                    <input type="hidden" name="same_column_indicator" value="0">
                    <input type="checkbox" name="same_column_indicator" value="1" data-tcf-same-column-toggle class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked($sameColumnIndicator)>
                    Same column for Time In / Time Out (Biometric indicator)
                </label>
                <p class="mt-1 text-xs text-gray-500">
                    One time column per row; indicator column determines punch direction (e.g. 1 = in, 0 = out).
                </p>

                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 {{ $sameColumnIndicator ? '' : 'hidden' }}" data-tcf-indicator-fields>
                    <div>
                        <label class="form-label">Time Column <span class="text-red-500">*</span></label>
                        <input type="number" name="worktime_column" min="1" max="99" value="{{ old('worktime_column', $mapping['worktime_column'] ?? '') }}" class="form-input" data-tcf-worktime-column>
                        @error('worktime_column')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Indicator Column <span class="text-red-500">*</span></label>
                        <input type="number" name="indicator_column" min="1" max="99" value="{{ old('indicator_column', $mapping['indicator_column'] ?? '') }}" class="form-input" data-tcf-indicator-column>
                        @error('indicator_column')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Time In Identifier <span class="text-red-500">*</span></label>
                        <input type="text" name="time_in_identifier" maxlength="1" value="{{ old('time_in_identifier', $mapping['time_in_identifier'] ?? '') }}" class="form-input uppercase" data-tcf-time-in-identifier placeholder="1">
                        @error('time_in_identifier')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Time Out Identifier <span class="text-red-500">*</span></label>
                        <input type="text" name="time_out_identifier" maxlength="1" value="{{ old('time_out_identifier', $mapping['time_out_identifier'] ?? '') }}" class="form-input uppercase" data-tcf-time-out-identifier placeholder="0">
                        @error('time_out_identifier')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-gray-900">Other Fields</h3>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary text-xs" data-tcf-custom-add>Add Field</button>
                <button type="button" class="btn-secondary text-xs" data-tcf-custom-remove>Remove</button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-[640px]">
                <thead>
                    <tr>
                        <th>Field Name</th>
                        <th>Column</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody data-tcf-custom-rows>
                    @foreach ($customFields as $index => $field)
                        <tr data-tcf-custom-row>
                            <td>
                                <input type="hidden" name="custom_fields[{{ $index }}][timecapture_field_id]" value="{{ $field['timecapture_field_id'] ?? '' }}">
                                <input type="text" name="custom_fields[{{ $index }}][field_name]" maxlength="50" value="{{ $field['field_name'] ?? '' }}" class="form-input">
                                @error('custom_fields.'.$index.'.field_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </td>
                            <td>
                                <input type="number" name="custom_fields[{{ $index }}][column]" min="1" max="99" value="{{ $field['column'] ?? '' }}" class="form-input max-w-[6rem]">
                                @error('custom_fields.'.$index.'.column')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </td>
                            <td>
                                <input type="text" name="custom_fields[{{ $index }}][description]" maxlength="255" value="{{ $field['description'] ?? '' }}" class="form-input">
                                @error('custom_fields.'.$index.'.description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <template data-tcf-custom-template>
            <tr data-tcf-custom-row>
                <td>
                    <input type="hidden" name="custom_fields[__INDEX__][timecapture_field_id]" value="">
                    <input type="text" name="custom_fields[__INDEX__][field_name]" maxlength="50" class="form-input">
                </td>
                <td>
                    <input type="number" name="custom_fields[__INDEX__][column]" min="1" max="99" class="form-input max-w-[6rem]">
                </td>
                <td>
                    <input type="text" name="custom_fields[__INDEX__][description]" maxlength="255" class="form-input">
                </td>
            </tr>
        </template>
    </div>

    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Save Changes' : 'Create' }}</button>
    </div>
</form>
