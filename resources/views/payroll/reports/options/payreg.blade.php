@php
    use App\Services\Reports\ReportBatchOptionsService;
@endphp

<div class="space-y-4" data-payroll-report-options="payreg">
    <h3 class="text-base font-semibold text-gray-900">Payroll Register Options</h3>
    <p class="text-sm text-gray-600">
        Preview, Excel, and PDF use the ICCT staff/admin payroll register column layout (days-based: basic rate, days, OT, late, gross, deductions, net).
        Excel download uses one worksheet per campus based on each employee’s <strong>Main assignment</strong>.
        Campuses with an <strong>Under Campus</strong> (for example Greenhills under Cainta) appear on the parent worksheet. Empty campuses are omitted.
    </p>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="employee-type" class="form-label">Employee Type</label>
            <select id="employee-type" name="employee_type" class="form-input" required>
                <option value="staff" @selected(old('employee_type', 'staff') === 'staff')>Staff</option>
                <option value="admin" @selected(old('employee_type') === 'admin')>Admin</option>
            </select>
            <p class="mt-1 text-xs text-gray-500">Only employees with the selected employment user type are included.</p>
            @error('employee_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <label for="payroll-batch-ids" class="form-label">Payroll Batch No.</label>
            <select
                id="payroll-batch-ids"
                name="payroll_batch_ids[]"
                class="form-input min-h-40"
                multiple
                required
            >
                @forelse ($processedBatches as $batch)
                    <option
                        value="{{ $batch->payroll_batch_id }}"
                        @selected(collect(old('payroll_batch_ids', []))->contains($batch->payroll_batch_id))
                    >
                        {{ app(ReportBatchOptionsService::class)->batchLabel($batch) }}
                    </option>
                @empty
                    <option value="" disabled>No processed or posted batches found</option>
                @endforelse
            </select>
            <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple batches.</p>
            @error('payroll_batch_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('payroll_batch_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="detail-columns" class="form-label">Add Details</label>
            <select
                id="detail-columns"
                name="detail_columns[]"
                class="form-input min-h-40"
                multiple
            >
                @foreach ($detailColumns as $key => $label)
                    @continue(in_array($key, ['employee_number', 'employee_name'], true))
                    <option value="{{ $key }}" @selected(collect(old('detail_columns', []))->contains($key))>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Employee No. and Employee Name are always included.</p>
            @error('detail_columns')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div>
            <label for="group-by" class="form-label">Group By</label>
            <select id="group-by" name="group_by" class="form-input">
                <option value="">None</option>
                @foreach ($groupColumns as $key => $label)
                    <option value="{{ $key }}" @selected(old('group_by') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="sort-by" class="form-label">Sort By</label>
            <select id="sort-by" name="sort_by" class="form-input">
                @foreach ($sortColumns as $key => $label)
                    <option value="{{ $key }}" @selected(old('sort_by', 'employee_name') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="output-format" class="form-label">Output</label>
            <select id="output-format" name="output_format" class="form-input" required>
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
