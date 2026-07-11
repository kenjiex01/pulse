@php
    $relation = $uploadConfig['detail_relation'];
    $records = $transaction->{$relation};
@endphp

<div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-xs text-gray-500">Batch No.</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->batch_no }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Pay Type</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->payrollCalendar?->payType?->pay_type ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Pay Period</p>
            <p class="mt-1 font-medium text-gray-900">
                @if ($transaction->payrollCalendar)
                    {{ $transaction->payrollCalendar->formattedPayPeriod() }}
                    · {{ $transaction->payrollCalendar->dt_from->format('M j') }} – {{ $transaction->payrollCalendar->dt_to->format('M j, Y') }}
                @else
                    —
                @endif
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Pay Year</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->payrollCalendar?->pay_year ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Uploaded By</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->uploadedBy?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Date Uploaded</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->dt_uploaded?->format('M j, Y g:i A') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">File Name</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->filename ?: '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">No. of Records</p>
            <p class="mt-1 font-medium text-gray-900">{{ $records->count() }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="table-skolaris min-w-full text-sm">
            <thead>
                <tr>
                    @if (in_array($uploadType, ['incomes', 'income-adjustments'], true))
                        <th class="px-3 py-2 text-left">Employee No.</th>
                        <th class="px-3 py-2 text-left">Employee Name</th>
                        <th class="px-3 py-2 text-left">Income Type</th>
                        <th class="px-3 py-2 text-right">Taxable</th>
                        <th class="px-3 py-2 text-right">Non-Taxable</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    @elseif (in_array($uploadType, ['deductions', 'deduction-adjustments'], true))
                        <th class="px-3 py-2 text-left">Employee No.</th>
                        <th class="px-3 py-2 text-left">Employee Name</th>
                        <th class="px-3 py-2 text-left">Deduction Type</th>
                        <th class="px-3 py-2 text-right">Hours</th>
                        <th class="px-3 py-2 text-right">Employee</th>
                        <th class="px-3 py-2 text-right">Employer</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    @elseif ($uploadType === 'hours-worked')
                        <th class="px-3 py-2 text-left">Employee No.</th>
                        <th class="px-3 py-2 text-left">Employee Name</th>
                        <th class="px-3 py-2 text-left">Day Type</th>
                        <th class="px-3 py-2 text-left">Time Type</th>
                        <th class="px-3 py-2 text-right">Hours</th>
                    @elseif ($uploadType === 'leaves')
                        <th class="px-3 py-2 text-left">Employee No.</th>
                        <th class="px-3 py-2 text-left">Employee Name</th>
                        <th class="px-3 py-2 text-left">Leave Type</th>
                        <th class="px-3 py-2 text-left">Date From</th>
                        <th class="px-3 py-2 text-left">Date To</th>
                        <th class="px-3 py-2 text-right">Leave Hours</th>
                    @elseif ($uploadType === 'loans')
                        <th class="px-3 py-2 text-left">Employee No.</th>
                        <th class="px-3 py-2 text-left">Employee Name</th>
                        <th class="px-3 py-2 text-left">Loan Type</th>
                        <th class="px-3 py-2 text-left">Loan Date</th>
                        <th class="px-3 py-2 text-right">Payment</th>
                        <th class="px-3 py-2 text-right">Penalty</th>
                    @else
                        <th class="px-3 py-2 text-left">Employee No.</th>
                        <th class="px-3 py-2 text-left">Employee Name</th>
                        <th class="px-3 py-2 text-left">Resignation Date</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="px-3 py-2 font-medium text-gray-900">{{ $record->employee?->employee_number ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $record->employee?->full_name ?? '—' }}</td>

                        @if (in_array($uploadType, ['incomes', 'income-adjustments'], true))
                            <td class="px-3 py-2 text-gray-600">{{ $record->incomeType?->income_type_code ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ $record->taxable !== null ? number_format((float) $record->taxable, 2) : '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ $record->non_taxable !== null ? number_format((float) $record->non_taxable, 2) : '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ $record->amount !== null ? number_format((float) $record->amount, 2) : '—' }}</td>
                        @elseif (in_array($uploadType, ['deductions', 'deduction-adjustments'], true))
                            <td class="px-3 py-2 text-gray-600">{{ $record->deductionType?->deduction_type_code ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ $record->hours !== null ? number_format((float) $record->hours, 2) : '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ $record->employee_amount !== null ? number_format((float) $record->employee_amount, 2) : '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ $record->employer_amount !== null ? number_format((float) $record->employer_amount, 2) : '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ $record->amount !== null ? number_format((float) $record->amount, 2) : '—' }}</td>
                        @elseif ($uploadType === 'hours-worked')
                            <td class="px-3 py-2 text-gray-600">{{ $record->dayType?->day_type_code ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $record->timeType?->time_type_code ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format((float) $record->hours, 2) }}</td>
                        @elseif ($uploadType === 'leaves')
                            <td class="px-3 py-2 text-gray-600">{{ $record->leaveType?->leave_type_code ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $record->dt_from?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $record->dt_to?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format((float) $record->leave_hours, 2) }}</td>
                        @elseif ($uploadType === 'loans')
                            <td class="px-3 py-2 text-gray-600">{{ $record->loanType?->loan_type_code ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $record->dt_loan?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ $record->payment !== null ? number_format((float) $record->payment, 2) : '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ $record->penalty !== null ? number_format((float) $record->penalty, 2) : '—' }}</td>
                        @else
                            <td class="px-3 py-2 text-gray-600">{{ $record->dt_resigned?->format('M j, Y') ?? '—' }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-gray-500">No detail records in this upload batch.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
