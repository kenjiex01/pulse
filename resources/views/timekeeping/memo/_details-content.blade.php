@if ($days === [])
    <p class="text-sm text-gray-500">No {{ strtolower(\App\Models\TimekeepingMemoSetup::labelForType($memoFilters['violation_type'])) }} records in this period.</p>
@else
    <form method="POST" action="{{ route(\App\Support\TimekeepingMemo::routeName('send'), [$employee->employee_id] + $query) }}" data-memo-detail-send-form>
        @csrf
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @can('memo.update')
                            <th class="px-3 py-2 text-left">
                                <input type="checkbox" data-memo-detail-select-all checked aria-label="Select all dates">
                            </th>
                        @endcan
                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Date</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Time in</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Time out</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Memo sent</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($days as $day)
                        <tr>
                            @can('memo.update')
                                <td class="px-3 py-2">
                                    <input
                                        type="checkbox"
                                        name="work_dates[]"
                                        value="{{ $day['work_date'] }}"
                                        data-memo-detail-checkbox
                                        checked
                                        aria-label="Include {{ $day['work_date'] }}"
                                    >
                                </td>
                            @endcan
                            <td class="px-3 py-2 text-gray-900">{{ \Illuminate\Support\Carbon::parse($day['work_date'])->format('M j, Y') }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $day['time_in'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $day['time_out'] ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @if ($day['memo_sent'])
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Yes</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">No</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-gray-500">If no dates are selected, all listed dates will be included when sending.</p>
        <div class="mt-4 flex justify-end gap-2">
            <button
                type="button"
                class="btn-secondary"
                data-memo-detail-preview
                data-url="{{ route(\App\Support\TimekeepingMemo::routeName('preview'), [$employee->employee_id] + $query) }}"
            >
                Preview
            </button>
            @can('memo.update')
                <button type="submit" class="btn-primary">Send Memo</button>
            @endcan
        </div>
    </form>
@endif
