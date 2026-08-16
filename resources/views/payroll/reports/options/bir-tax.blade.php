@php
    use App\Services\Reports\ReportBatchOptionsService;
@endphp

<div class="space-y-4" data-payroll-report-options="bir-tax">
    <h3 class="text-base font-semibold text-gray-900">BIR Employees&apos; Tax Withheld Options</h3>
    <p class="text-sm text-gray-600">
        Uses employee salary <strong>Is Above minimum wage earner</strong> to place compensation into MWE Income or Taxable Inc. columns.
        Amounts that exceed the TRAIN exempt threshold go to Taxable Inc. with W/T (and Tax Withheld).
        De minimis Benefit comes from income types named Deminimis (Allowance class).
    </p>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="bir-tax-payroll-batch-ids" class="form-label">Payroll Batch No.</label>
            <select
                id="bir-tax-payroll-batch-ids"
                name="payroll_batch_ids[]"
                class="form-input min-h-40"
                multiple
                required
            >
                @forelse ($processedBatches as $batch)
                    <option
                        value="{{ $batch->payroll_batch_id }}"
                        data-pay-year="{{ $batch->payrollCalendar?->pay_year }}"
                        data-calendar-month="{{ $batch->payrollCalendar?->calendar_month }}"
                        @selected(collect(old('payroll_batch_ids', []))->contains($batch->payroll_batch_id))
                    >
                        {{ app(ReportBatchOptionsService::class)->batchLabel($batch) }}
                    </option>
                @empty
                    <option value="" disabled>No processed or posted batches found</option>
                @endforelse
            </select>
            <p class="mt-1 text-xs text-gray-500">
                Hold Ctrl/Cmd to select multiple batches. Selected batches must share the same pay month and pay year.
            </p>
            @error('payroll_batch_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('payroll_batch_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="bir-tax-output-format" class="form-label">Output</label>
            <select id="bir-tax-output-format" name="output_format" class="form-input" required>
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
