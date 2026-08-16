@php
    use App\Services\Reports\ReportBatchOptionsService;
@endphp

<div class="space-y-4" data-payroll-report-options="payslip">
    <h3 class="text-base font-semibold text-gray-900">Payslip Options</h3>
    <p class="text-sm text-gray-600">
        Select a <strong>posted</strong> payroll batch and one or more employees. Only income and deduction types with amounts are shown on the payslip.
    </p>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="payslip-payroll-batch-id" class="form-label">Posted Payroll Batch</label>
            <select
                id="payslip-payroll-batch-id"
                name="payroll_batch_id"
                class="form-input"
                required
                data-payslip-batch-select
                data-employees-url="{{ route('payroll.reports.batch-employees') }}"
            >
                <option value="">Select posted batch…</option>
                @foreach (($postedBatches ?? collect()) as $batch)
                    <option
                        value="{{ $batch->payroll_batch_id }}"
                        @selected((string) old('payroll_batch_id') === (string) $batch->payroll_batch_id)
                    >
                        {{ app(ReportBatchOptionsService::class)->batchLabel($batch) }}
                    </option>
                @endforeach
            </select>
            @error('payroll_batch_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="payslip-output-format" class="form-label">Output</label>
            <select id="payslip-output-format" name="output_format" class="form-input" required>
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
        <label for="payslip-employee-ids" class="form-label">Employee(s)</label>
        <select
            id="payslip-employee-ids"
            name="employee_ids[]"
            class="form-input min-h-48"
            multiple
            required
            data-payslip-employee-select
        >
            @if (old('payroll_batch_id'))
                <option value="" disabled>Select a posted batch first…</option>
            @else
                <option value="" disabled>Select a posted batch to load employees…</option>
            @endif
        </select>
        <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple employees from the chosen batch.</p>
        @error('employee_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('employee_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
