<div class="space-y-4" data-payroll-report-options="employee-credentials">
    <h3 class="text-base font-semibold text-gray-900">Employee Options</h3>
    <p class="text-sm text-gray-600">
        Lists all employee details: personal, contact, address, campus assignments, employment, salary, shift / timekeeping, and loans.
    </p>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="credentials-employee-ids" class="form-label">Employee</label>
            <select
                id="credentials-employee-ids"
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
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div>
            <label for="credentials-output-format" class="form-label">Output</label>
            <select id="credentials-output-format" name="output_format" class="form-input" required>
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
</div>
