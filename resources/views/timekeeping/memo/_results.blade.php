@php
    $query = request()->only(['date_from', 'date_to', 'violation_type', 'min_count', 'search']);
@endphp

@if (($rows ?? []) === [] || count($rows) === 0)
    <div class="rounded-xl border border-gray-200 bg-white px-5 py-10 text-center text-sm text-gray-500">
        No employees matched the selected filters.
    </div>
@else
    @can('memo.update')
        <form id="memo-batch-form" method="POST" action="{{ route(\App\Support\TimekeepingMemo::routeName('batch-send'), $query) }}" data-memo-batch-form class="mb-3 flex justify-end">
            @csrf
            <button type="submit" class="btn-primary" data-memo-batch-send disabled>Send Selected</button>
        </form>
    @endcan

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @can('memo.update')
                            <th class="px-4 py-3 text-left">
                                <input type="checkbox" data-memo-select-all aria-label="Select all">
                            </th>
                        @endcan
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Employee</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Campus</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Count</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        @php $employee = $row['employee']; @endphp
                        <tr>
                            @can('memo.update')
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        name="employee_ids[]"
                                        value="{{ $employee->employee_id }}"
                                        form="memo-batch-form"
                                        data-memo-row-checkbox
                                        aria-label="Select {{ $employee->full_name }}"
                                    >
                                </td>
                            @endcan
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $employee->full_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $row['campus'] }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $row['count'] }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="btn-secondary px-3 py-1.5 text-xs"
                                        data-memo-view
                                        data-url="{{ route(\App\Support\TimekeepingMemo::routeName('details'), [$employee->employee_id] + $query) }}"
                                    >
                                        View
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-secondary px-3 py-1.5 text-xs"
                                        data-memo-preview
                                        data-url="{{ route(\App\Support\TimekeepingMemo::routeName('preview'), [$employee->employee_id] + $query) }}"
                                    >
                                        Preview
                                    </button>
                                    @can('memo.update')
                                        <form method="POST" action="{{ route(\App\Support\TimekeepingMemo::routeName('send'), [$employee->employee_id] + $query) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn-primary px-3 py-1.5 text-xs">Send</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($paginator)
        <div class="mt-4">
            {{ $paginator->links() }}
        </div>
    @endif
@endif
