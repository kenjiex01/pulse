<div class="space-y-4" data-payroll-report-options="historical-data">
    <h3 class="text-base font-semibold text-gray-900">Historical Data Options</h3>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="historical-employee-ids" class="form-label">Employee</label>
            <select
                id="historical-employee-ids"
                name="employee_ids[]"
                class="form-input min-h-40"
                multiple
            >
                @foreach ($employees as $employee)
                    <option
                        value="{{ $employee->employee_id }}"
                        @selected(collect(old('employee_ids', []))->contains($employee->employee_id))
                    >
                        {{ $employee->employee_number }} — {{ $employee->full_name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Leave blank to include all employees. Hold Ctrl/Cmd to select multiple.</p>
            @error('employee_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('employee_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="space-y-4">
            <div>
                <label for="historical-actions" class="form-label">Actions</label>
                <select
                    id="historical-actions"
                    name="actions[]"
                    class="form-input min-h-28"
                    multiple
                >
                    @foreach (['create' => 'Created', 'update' => 'Updated', 'delete' => 'Deleted'] as $value => $label)
                        <option
                            value="{{ $value }}"
                            @selected(collect(old('actions', []))->contains($value))
                        >
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Leave blank to include all actions.</p>
                @error('actions')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @error('actions.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="historical-date-from" class="form-label">Date From</label>
                    <input
                        type="date"
                        id="historical-date-from"
                        name="date_from"
                        class="form-input"
                        value="{{ old('date_from') }}"
                    >
                    @error('date_from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="historical-date-to" class="form-label">Date To</label>
                    <input
                        type="date"
                        id="historical-date-to"
                        name="date_to"
                        class="form-input"
                        value="{{ old('date_to') }}"
                    >
                    @error('date_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div>
            <label for="historical-output-format" class="form-label">Output</label>
            <select id="historical-output-format" name="output_format" class="form-input" required>
                @foreach ($report->fileTypes as $fileType)
                    <option
                        value="{{ $fileType->code }}"
                        @selected(old('output_format', 'html') === $fileType->code)
                    >
                        {{ $fileType->label }}
                    </option>
                @endforeach
            </select>
            @error('output_format')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <p class="text-xs text-gray-500">
        Shows employee change history from system logs with previous and new values per field.
    </p>
</div>
