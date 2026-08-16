@php
    $pickerPrefix = 'bir-2316';
    $payYears = $payYears ?? [];
@endphp

<div class="space-y-4" data-payroll-report-options="{{ $pickerPrefix }}" data-batch-employee-picker>
    <h3 class="text-base font-semibold text-gray-900">BIR Form 2316 Options</h3>
    <p class="text-sm text-gray-600">
        Certificate of Compensation Payment / Tax Withheld. Select a <strong>payroll year</strong> and employees.
        Amounts are the sum of all <strong>posted</strong> payroll batches in that year. Preview/PDF uses the official BIR blank form.
    </p>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="{{ $pickerPrefix }}-pay-year" class="form-label">Payroll Year</label>
            <select
                id="{{ $pickerPrefix }}-pay-year"
                name="pay_year"
                class="form-input"
                required
                data-batch-employee-batch-select
                data-employees-param="pay_year"
                data-employees-empty-filter="Select a payroll year to load employees…"
                data-employees-empty-results="No employees with posted payroll in this year."
                data-employees-url="{{ route('payroll.reports.year-employees') }}"
            >
                <option value="">Select payroll year…</option>
                @foreach ($payYears as $year)
                    <option value="{{ $year }}" @selected((string) old('pay_year') === (string) $year)>
                        {{ $year }}
                    </option>
                @endforeach
            </select>
            @error('pay_year')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="{{ $pickerPrefix }}-output-format" class="form-label">Output</label>
            <select id="{{ $pickerPrefix }}-output-format" name="output_format" class="form-input" required>
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

    <div>
        <label for="{{ $pickerPrefix }}-employee-ids" class="form-label">Employee(s)</label>
        <select
            id="{{ $pickerPrefix }}-employee-ids"
            name="employee_ids[]"
            class="form-input min-h-48"
            multiple
            required
            data-batch-employee-employee-select
        >
            <option value="" disabled>Select a payroll year to load employees…</option>
        </select>
        <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select one or more employees. Only selected employees will be included in the report.</p>
        @error('employee_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('employee_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
