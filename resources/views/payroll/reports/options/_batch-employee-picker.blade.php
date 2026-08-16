@php
    use App\Services\Reports\ReportBatchOptionsService;

    $pickerPrefix = $pickerPrefix ?? 'bir';
    $title = $title ?? 'BIR Report Options';
    $help = $help ?? 'Select posted payroll batches, then choose which employees to include before generating.';
    $oldBatchIds = collect(old('payroll_batch_ids', []))->map(fn ($id) => (string) $id);
    $oldEmployeeIds = collect(old('employee_ids', []))->map(fn ($id) => (string) $id)->filter()->values();
@endphp

<div
    class="space-y-4"
    data-payroll-report-options="{{ $pickerPrefix }}"
    data-batch-employee-picker
>
    <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
    <p class="text-sm text-gray-600">{!! $help !!}</p>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="{{ $pickerPrefix }}-payroll-batch-ids" class="form-label">Posted Payroll Batch</label>
            <select
                id="{{ $pickerPrefix }}-payroll-batch-ids"
                name="payroll_batch_ids[]"
                class="form-input min-h-40"
                multiple
                required
                data-batch-employee-batch-select
                data-payroll-batch-month-guard
                data-employees-url="{{ route('payroll.reports.batch-employees') }}"
                data-employees-param="payroll_batch_ids[]"
                data-employees-empty-filter="Select posted batch(es) to load employees…"
                data-employees-empty-results="No employees found in the selected batch(es)."
                data-selected-employee-ids="{{ $oldEmployeeIds->implode(',') }}"
            >
                @forelse (($postedBatches ?? collect()) as $batch)
                    <option
                        value="{{ $batch->payroll_batch_id }}"
                        data-pay-year="{{ $batch->payrollCalendar?->pay_year }}"
                        data-calendar-month="{{ $batch->payrollCalendar?->calendar_month }}"
                        @selected($oldBatchIds->contains((string) $batch->payroll_batch_id))
                    >
                        {{ app(ReportBatchOptionsService::class)->batchLabel($batch) }}
                    </option>
                @empty
                    <option value="" disabled>No posted batches found</option>
                @endforelse
            </select>
            <p class="mt-1 text-xs text-gray-500">
                Hold Ctrl/Cmd to select multiple batches. Selected batches must share the same pay month and pay year; amounts for the same employee are summed.
            </p>
            @error('payroll_batch_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('payroll_batch_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
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
            <option value="" disabled>Select posted batch(es) to load employees…</option>
        </select>
        <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select one or more employees. Only selected employees will be included in the report.</p>
        @error('employee_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('employee_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
