@php
    use App\Support\PayrollCalendarModule;

    $openViewId = (string) request('view', old('view_period_id', $openViewId ?? ''));
@endphp

<div data-live-table-total-update data-total="{{ $periods->total() }}" hidden></div>

<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
        <h3 class="text-sm font-semibold text-gray-900">Pay Periods</h3>
        @can('payroll-calendar.delete')
            <form
                id="payroll-calendar-bulk-delete"
                method="POST"
                action="{{ route(PayrollCalendarModule::routeName('bulk-destroy'), ['payType' => $payTypeSlug]) }}"
                onsubmit="return confirm('Delete selected pay periods?')"
                class="shrink-0"
            >
                @csrf
                @method('DELETE')
                <input type="hidden" name="year" value="{{ $year }}">
                <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs text-red-600" disabled data-bulk-delete-btn>Delete Selected</button>
            </form>
        @endcan
    </div>

    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-full">
            <thead>
                <tr>
                    @can('payroll-calendar.delete')
                        <th class="w-10">
                            <input type="checkbox" data-bulk-select-all aria-label="Select all">
                        </th>
                    @endcan
                    <th>Pay Period</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Month</th>
                    <th>Regular</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($periods as $period)
                    <tr>
                        @can('payroll-calendar.delete')
                            <td>
                                <input
                                    type="checkbox"
                                    name="period_ids[]"
                                    value="{{ $period->payroll_calendar_id }}"
                                    form="payroll-calendar-bulk-delete"
                                    data-bulk-select-item
                                    aria-label="Select pay period {{ $period->formattedPayPeriod() }}"
                                >
                            </td>
                        @endcan
                        <td class="font-medium text-gray-900">{{ $period->formattedPayPeriod() }}</td>
                        <td class="whitespace-nowrap text-gray-600">{{ $period->dt_from->format('M j, Y') }}</td>
                        <td class="whitespace-nowrap text-gray-600">{{ $period->dt_to->format('M j, Y') }}</td>
                        <td class="text-gray-600">{{ PayrollCalendarModule::shortMonthLabel((int) $period->calendar_month) }}</td>
                        <td class="text-gray-600">{{ $period->is_regular_period ? 'Yes' : '—' }}</td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                <button
                                    type="button"
                                    data-modal-open="payroll-calendar-schedule-{{ $payTypeSlug }}-{{ $period->payroll_calendar_id }}"
                                    class="btn-icon"
                                    title="View schedule"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                                @can('payroll-calendar.update')
                                    <button type="button" data-modal-open="payroll-calendar-edit-{{ $payTypeSlug }}-{{ $period->payroll_calendar_id }}" class="btn-icon" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                @endcan
                                @can('payroll-calendar.delete')
                                    <form method="POST" action="{{ route(PayrollCalendarModule::routeName('destroy'), ['payType' => $payTypeSlug, 'period' => $period->payroll_calendar_id]) }}" onsubmit="return confirm('Delete this pay period?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon text-red-500 hover:bg-red-50 hover:text-red-600" title="Delete">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->can('payroll-calendar.delete') ? 7 : 6 }}" class="py-16 text-center text-sm text-gray-500">
                            No pay periods found for {{ $year }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($periods->hasPages())
        <div class="border-t border-gray-100 px-4 py-3">
            @include('partials.data-table-pagination', ['paginator' => $periods])
        </div>
    @endif
</div>

<div data-live-table-modals>
    @foreach ($periods as $period)
        @include('partials.modal', [
            'id' => "payroll-calendar-schedule-{$payTypeSlug}-{$period->payroll_calendar_id}",
            'title' => 'Loan & Deduction Schedule',
            'description' => 'Pay Period '.$period->formattedPayPeriod().' · '.$period->dt_from->format('M j, Y').' – '.$period->dt_to->format('M j, Y'),
            'open' => $openViewId === (string) $period->payroll_calendar_id,
            'panelClass' => 'max-w-3xl',
            'body' => view('payroll.calendar._schedule-form', [
                'payTypeSlug' => $payTypeSlug,
                'period' => $period,
                'deductionTypes' => $deductionTypes,
                'loanTypes' => $loanTypes,
            ])->render(),
        ])

        @can('payroll-calendar.update')
            @include('partials.modal', [
                'id' => "payroll-calendar-edit-{$payTypeSlug}-{$period->payroll_calendar_id}",
                'title' => 'Edit Pay Period',
                'description' => PayrollCalendarModule::payTypeLabel($payTypeId).' · Period '.$period->formattedPayPeriod(),
                'open' => (string) old('edit_period_id', $openEditId ?? '') === (string) $period->payroll_calendar_id && $errors->any(),
                'panelClass' => 'max-w-3xl',
                'body' => view('payroll.calendar._form', [
                    'payTypeSlug' => $payTypeSlug,
                    'payTypeId' => $payTypeId,
                    'year' => $year,
                    'period' => $period,
                    'isEdit' => true,
                    'formContext' => "edit-{$payTypeSlug}",
                    'months' => $months,
                    'collegeSelect' => $collegeSelect,
                    'userTypeOptions' => $userTypeOptions,
                    'nextPayPeriod' => $period->pay_period,
                ])->render(),
            ])
        @endcan
    @endforeach
</div>
