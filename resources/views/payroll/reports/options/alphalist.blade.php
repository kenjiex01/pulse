@php
    $pickerPrefix = 'alphalist';
    $payYears = $payYears ?? [];
    $scheduleChoices = [
        '7.1' => '7.1 — Employees terminated before December 31',
        '7.3' => '7.3 — Employees as of Dec 31 (no previous employer)',
        '7.4' => '7.4 — Employees as of Dec 31 (with previous employer)',
        '7.5' => '7.5 — Minimum wage earners',
    ];
    $selectedSchedules = collect(old('schedules', array_keys($scheduleChoices)))
        ->map(fn ($value) => (string) $value)
        ->all();
@endphp

<div class="space-y-4" data-payroll-report-options="{{ $pickerPrefix }}">
    <h3 class="text-base font-semibold text-gray-900">Alphalist Options</h3>
    <p class="text-sm text-gray-600">
        Alphabetical list of employees for the selected <strong>payroll year</strong>.
        Includes all employees with <strong>posted</strong> payroll in that year. Employer TIN comes from BIR Forms Setup.
        Choose one or more schedules; Excel includes a sheet for each selected schedule.
    </p>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="{{ $pickerPrefix }}-pay-year" class="form-label">Applicable Year</label>
            <select
                id="{{ $pickerPrefix }}-pay-year"
                name="pay_year"
                class="form-input"
                required
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
            <label for="{{ $pickerPrefix }}-schedules" class="form-label">Schedule(s)</label>
            <select
                id="{{ $pickerPrefix }}-schedules"
                name="schedules[]"
                class="form-input"
                size="4"
                multiple
                required
                style="height: auto !important; min-height: 6.75rem;"
            >
                @foreach ($scheduleChoices as $code => $label)
                    <option value="{{ $code }}" @selected(in_array($code, $selectedSchedules, true))>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple. Default: all schedules.</p>
            @error('schedules')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('schedules.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="{{ $pickerPrefix }}-day-factor" class="form-label">Day Factor (optional)</label>
            <input
                id="{{ $pickerPrefix }}-day-factor"
                type="number"
                name="day_factor"
                class="form-input"
                min="1"
                max="366"
                step="1"
                value="{{ old('day_factor', 313) }}"
            >
            <p class="mt-1 text-xs text-gray-500">Used on Schedule 7.5 (factor days/year). Default 313.</p>
            @error('day_factor')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="{{ $pickerPrefix }}-output-format" class="form-label">Output</label>
            <select id="{{ $pickerPrefix }}-output-format" name="output_format" class="form-input" required>
                @foreach ($report->fileTypes as $fileType)
                    <option
                        value="{{ $fileType->code }}"
                        @selected(old('output_format', 'excel') === $fileType->code)
                    >
                        {{ $fileType->label }}
                    </option>
                @endforeach
            </select>
            @error('output_format')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
