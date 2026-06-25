<div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-xs text-gray-500">Batch No.</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->formattedBatchNo() }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Pay Type</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->payrollCalendar?->payType?->pay_type ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Pay Period</p>
            <p class="mt-1 font-medium text-gray-900">
                @if ($batch->payrollCalendar)
                    {{ $batch->payrollCalendar->formattedPayPeriod() }}
                    · {{ $batch->payrollCalendar->dt_from->format('M j') }} – {{ $batch->payrollCalendar->dt_to->format('M j, Y') }}
                @else
                    —
                @endif
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Pay Year</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->payrollCalendar?->pay_year ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Tax Computation</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->withholdingTaxComputation?->withholding_tax_computation ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Status</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->status?->payroll_batch_status ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Created By</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->createdBy?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Date Created</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->dt_created?->format('M j, Y g:i A') ?? '—' }}</p>
        </div>
    </div>

    @if (! $batchEditable)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            This batch can no longer be modified. Employee add/remove is disabled.
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            @if ($batchEditable)
                <button
                    type="button"
                    class="btn-primary !px-3 !py-1.5 text-xs"
                    data-modal-open="payroll-batch-add-employees-modal"
                >
                    Add Employees
                </button>
                <button
                    type="submit"
                    form="payroll-batch-remove-employees-form"
                    class="btn-secondary !px-3 !py-1.5 text-xs"
                    disabled
                    data-payroll-batch-remove-btn
                >
                    Remove Selected
                </button>
                <span class="text-xs text-gray-500" data-payroll-batch-selected-count>0 selected</span>
            @endif
        </div>

        <form
            method="GET"
            action="{{ route(\App\Support\PayrollTransactionModule::routeName('tab'), ['tab' => 'batches']) }}"
            class="flex items-center gap-2"
        >
            <input type="hidden" name="view_payroll_batch" value="{{ $batch->payroll_batch_id }}">
            <input type="hidden" name="search" value="{{ request('search') }}">
            @if (request('view_batch_detail'))
                <input type="hidden" name="view_batch_detail" value="{{ request('view_batch_detail') }}">
            @endif
            <label for="batch-employee-search" class="sr-only">Search employees in batch</label>
            <input
                type="search"
                id="batch-employee-search"
                name="batch_employee_search"
                value="{{ $batchEmployeeSearch }}"
                placeholder="Search employee no. or name..."
                class="form-input !py-1.5 text-sm"
            >
            <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Search</button>
        </form>
    </div>

    @if ($batchEditable)
        <form
            id="payroll-batch-remove-employees-form"
            method="POST"
            action="{{ route(\App\Support\PayrollTransactionModule::routeName('employees.destroy'), $batch) }}"
            data-payroll-batch-remove-form
            onsubmit="return confirm('Remove selected employees from this batch?');"
        >
            @csrf
            @method('DELETE')
            <input type="hidden" name="batch_employee_search" value="{{ $batchEmployeeSearch }}">
    @endif

    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="table-skolaris min-w-full text-sm">
            <thead>
                <tr>
                    @if ($batchEditable)
                        <th class="w-10 px-3 py-2">
                            <input type="checkbox" data-payroll-batch-select-all aria-label="Select all employees">
                        </th>
                    @endif
                    <th class="px-3 py-2 text-left">Employee No.</th>
                    <th class="px-3 py-2 text-left">Employee Name</th>
                    <th class="w-16 px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batchEmployees as $detail)
                    <tr>
                        @if ($batchEditable)
                            <td class="px-3 py-2">
                                <input
                                    type="checkbox"
                                    name="detail_ids[]"
                                    value="{{ $detail->payroll_batch_detail_id }}"
                                    form="payroll-batch-remove-employees-form"
                                    data-payroll-batch-row-select
                                    aria-label="Select {{ $detail->employee?->full_name ?? 'employee' }}"
                                >
                            </td>
                        @endif
                        <td class="px-3 py-2 font-medium text-gray-900">{{ $detail->employee?->employee_number ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $detail->employee?->full_name ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">
                            <a
                                href="{{ route(\App\Support\PayrollTransactionModule::routeName('employees.show'), [$batch, $detail->payroll_batch_detail_id]) }}?batch_employee_search={{ urlencode($batchEmployeeSearch) }}&search={{ urlencode(request('search', '')) }}"
                                class="btn-icon"
                                title="View employee payroll detail"
                                data-no-loader
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $batchEditable ? 4 : 3 }}" class="px-3 py-8 text-center text-gray-500">
                            No employees in this batch yet.
                            @if ($batchEditable)
                                Click <strong>Add Employees</strong> to assign employees.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($batchEditable)
        </form>
    @endif

    @if ($batchEmployees->hasPages())
        <div class="border-t border-gray-100 pt-3">
            @include('partials.data-table-pagination', ['paginator' => $batchEmployees])
        </div>
    @endif
</div>
