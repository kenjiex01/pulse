@php
    use App\Support\PayrollCalendarModule;
@endphp

<div data-live-table-total-update data-total="{{ $priorities->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[720px]">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Type</th>
                    <th>Deduction/Loan Description</th>
                    <th class="text-right">Set Priority</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($priorities as $priority)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $priority->priority }}</td>
                        <td class="text-gray-600">{{ $priority->typeLabel() }}</td>
                        <td class="text-gray-700">{{ $priority->descriptionLabel() }}</td>
                        <td>
                            @can('payroll-calendar.update')
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route(PayrollCalendarModule::routeName('move-priority'), $priority) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" class="btn-icon" title="Move up">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route(PayrollCalendarModule::routeName('move-priority'), $priority) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" class="btn-icon" title="Move down">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                    </form>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-12 text-center text-sm text-gray-500">No deduction or loan priority records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="datatable-skolaris-pagination mt-4">
    @include('partials.data-table-pagination', ['paginator' => $priorities])
</div>
