<div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-xs text-gray-500">Pull Batch</p>
            <p class="mt-1 font-medium text-gray-900">Batch #{{ $batch->formattedBatchNo() }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Date Pulled</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->pulled_at?->format('M j, Y g:i A') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Pulled By</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->pulledBy?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Date Range</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->dateRangeLabel() }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Employees Pulled</p>
            <p class="mt-1 font-medium text-gray-900">{{ $employeeSummaries->count() }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Load Rows</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->records_count ?? $batch->sessions->count() }}</p>
        </div>
    </div>

    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Employees in this pull</h3>
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Employee No.</th>
                        <th class="px-3 py-2 text-left">Name</th>
                        <th class="px-3 py-2 text-left">Rows Pulled</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employeeSummaries as $summary)
                        <tr>
                            <td class="px-3 py-2 text-gray-900">{{ $summary['employee_number'] }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $summary['employee_name'] }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $summary['rows_count'] }}</td>
                            <td class="px-3 py-2 text-right">
                                <a
                                    href="{{ route(\App\Support\TimeLogs::routeName('tab'), ['tab' => \App\Support\TimeLogs::TEACHING_LOADS_TAB, 'view_pull' => $batch->teaching_load_pull_batch_id, 'pull_batch' => $batch->teaching_load_pull_batch_id, 'view_pull_employee' => $summary['employee_id']]) }}"
                                    class="btn-secondary !px-3 !py-1 text-xs"
                                    data-no-loader
                                >
                                    View Loads
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-gray-500">No employees found for this pull.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
