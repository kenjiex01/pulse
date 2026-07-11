<form
    method="POST"
    action="{{ route(\App\Support\PayrollTransactionModule::routeName('employees.store'), $batch) }}"
    class="space-y-4"
    data-payroll-batch-add-form
>
    @csrf
    <input type="hidden" name="form_context" value="add-payroll-batch-employees">
    <input type="hidden" name="payroll_batch_id" value="{{ $batch->payroll_batch_id }}">

    <div class="flex flex-wrap items-end gap-2">
        <div class="min-w-[220px] flex-1">
            <label for="add-employee-search" class="form-label">Search</label>
            <input
                type="search"
                id="add-employee-search"
                name="add_employee_search"
                value="{{ $addEmployeeSearch }}"
                placeholder="Employee no. or name..."
                class="form-input"
                form="payroll-batch-add-search-form"
            >
        </div>
        <button type="submit" form="payroll-batch-add-search-form" class="btn-secondary">Search</button>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
        <label class="inline-flex items-start gap-2 text-sm text-gray-700">
            <input
                type="checkbox"
                name="include_all_employees"
                value="1"
                class="mt-0.5 rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                data-payroll-batch-include-all
                @checked(old('include_all_employees'))
            >
            <span>
                Include all eligible employees
                <span class="block text-xs text-gray-500">Adds all active employees with the same pay type not yet in another batch for this pay period.</span>
            </span>
        </label>
    </div>

    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700">
            <input type="checkbox" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" data-payroll-batch-add-select-all>
            Select all shown
        </label>
        <p class="text-xs text-gray-500" data-payroll-batch-add-selected-count>0 selected</p>
    </div>

    <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200" data-payroll-batch-add-picker>
        @forelse ($eligibleEmployees as $employee)
            <label class="flex cursor-pointer items-center gap-3 border-b border-gray-100 px-3 py-2 text-sm last:border-b-0 hover:bg-gray-50">
                <input
                    type="checkbox"
                    name="employee_ids[]"
                    value="{{ $employee->employee_id }}"
                    class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                    data-payroll-batch-add-row
                    @checked(in_array($employee->employee_id, old('employee_ids', []), true))
                >
                <span class="min-w-[7rem] font-medium text-gray-900">{{ $employee->employee_number }}</span>
                <span class="text-gray-600">{{ $employee->full_name }}</span>
            </label>
        @empty
            <p class="px-4 py-8 text-center text-sm text-gray-500">
                {{ $addEmployeesEmptyMessage ?? 'No eligible employees found for this batch.' }}
            </p>
        @endforelse
    </div>

    @if ($eligibleEmployees->count() >= 200)
        <p class="text-xs text-gray-500">Showing first 200 results. Use search to narrow the list.</p>
    @endif

    @error('employee_ids')
        <p class="form-error">{{ $message }}</p>
    @enderror

    <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
        <button type="button" class="btn-secondary w-full sm:w-auto" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary w-full sm:w-auto" data-payroll-batch-add-submit>Add Selected</button>
    </div>
</form>

<form
    id="payroll-batch-add-search-form"
    method="GET"
    action="{{ route(\App\Support\PayrollTransactionModule::routeName('tab'), ['tab' => 'batches']) }}"
    class="hidden"
>
    <input type="hidden" name="view_payroll_batch" value="{{ $batch->payroll_batch_id }}">
    <input type="hidden" name="add_employees" value="1">
    <input type="hidden" name="search" value="{{ request('search') }}">
    <input type="hidden" name="batch_employee_search" value="{{ $batchEmployeeSearch }}">
</form>
