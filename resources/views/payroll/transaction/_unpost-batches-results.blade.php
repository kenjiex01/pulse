<div data-live-table-total-update data-total="{{ $batches->total() }}" hidden></div>

<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 px-4 py-3">
        <h3 class="text-sm font-semibold text-gray-900">Posted Batches</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-full">
            <thead>
                <tr>
                    <th>Batch No.</th>
                    <th>Pay Type</th>
                    <th>Pay Period</th>
                    <th>Pay Year</th>
                    <th>Employees</th>
                    <th>Regular</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Date Created</th>
                    <th class="w-28 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $batch->formattedBatchNo() }}</td>
                        <td class="text-gray-600">{{ $batch->payrollCalendar?->payType?->pay_type ?? '—' }}</td>
                        <td class="whitespace-nowrap text-gray-600">
                            @if ($batch->payrollCalendar)
                                {{ $batch->payrollCalendar->formattedPayPeriod() }}
                                · {{ $batch->payrollCalendar->dt_from->format('M j') }} – {{ $batch->payrollCalendar->dt_to->format('M j, Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-gray-600">{{ $batch->payrollCalendar?->pay_year ?? '—' }}</td>
                        <td class="text-gray-600">{{ $batch->details_count }}</td>
                        <td class="text-gray-600">{{ $batch->payrollCalendar?->is_regular_period ? 'Yes' : '—' }}</td>
                        <td class="text-gray-600">{{ $batch->status?->payroll_batch_status ?? '—' }}</td>
                        <td class="text-gray-600">{{ $batch->createdBy?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap text-gray-600">{{ $batch->dt_created?->format('M j, Y g:i A') ?? '—' }}</td>
                        <td class="text-right">
                            <form
                                method="POST"
                                action="{{ route(\App\Support\PayrollTransactionModule::routeName('unpost'), $batch) }}"
                                class="inline"
                                data-confirm-submit="Unpost batch {{ $batch->formattedBatchNo() }}? It will return to Processed and can be re-processed or edited again."
                            >
                                @csrf
                                <input type="hidden" name="search" value="{{ request('search') }}">
                                <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Unpost</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="py-16 text-center text-sm text-gray-500">
                            No posted batches found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($batches->hasPages())
        <div class="border-t border-gray-100 px-4 py-3">
            @include('partials.data-table-pagination', ['paginator' => $batches])
        </div>
    @endif
</div>
