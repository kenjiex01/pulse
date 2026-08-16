@php
    use App\Services\Reports\ReportBatchOptionsService;
@endphp

<div class="space-y-4" data-payroll-report-options="phil">
    <h3 class="text-base font-semibold text-gray-900">PhilHealth Contribution Options</h3>
    <p class="text-sm text-gray-600">
        Preview, Excel, and PDF use the ICCT PhilHealth contribution payment return layout. Excel worksheet name: <strong>Philhealth</strong>.
    </p>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="phil-payroll-batch-ids" class="form-label">Payroll Batch No.</label>
            <select
                id="phil-payroll-batch-ids"
                name="payroll_batch_ids[]"
                class="form-input min-h-40"
                multiple
                required
                data-payroll-batch-month-guard
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
                Hold Ctrl/Cmd to select multiple batches. Selected batches must share the same pay month and pay year; amounts for the same employee are summed.
            </p>
            @error('payroll_batch_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('payroll_batch_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="phil-output-format" class="form-label">Output</label>
            <select id="phil-output-format" name="output_format" class="form-input" required>
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
