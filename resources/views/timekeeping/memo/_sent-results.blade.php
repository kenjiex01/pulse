@php
    $clientPerPage = $paginator?->perPage() ?? 10;
@endphp

@if (($sentRows ?? collect())->isEmpty())
    <div class="rounded-xl border border-gray-200 bg-white px-5 py-10 text-center text-sm text-gray-500">
        No memos have been sent.
    </div>
@else
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Sent</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Control number</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Memo type</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Employee</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Violation</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Sent by</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($sentRows as $row)
                        <tr
                            data-memo-row
                            data-memo-form-id="{{ $row['form_id'] }}"
                            data-memo-control="{{ strtolower($row['control_no']) }}"
                            data-memo-search="{{ $row['search'] }}"
                            @class(['hidden' => $loop->index >= $clientPerPage])
                        >
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $row['sent_label'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">{{ $row['control_no'] !== '' ? $row['control_no'] : '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                <div>{{ $row['form_name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $row['source_label'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-900">
                                <div class="font-medium">{{ $row['employee_name'] }}</div>
                                @if ($row['employee_number'] !== '')
                                    <div class="text-xs text-gray-500">{{ $row['employee_number'] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $row['violation_label'] !== '' ? $row['violation_label'] : '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $row['sender_name'] !== '' ? $row['sender_name'] : '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end">
                                    @if ($row['submission_id'])
                                        <button
                                            type="button"
                                            class="btn-secondary px-3 py-1.5 text-xs"
                                            data-memo-preview
                                            data-url="{{ route(\App\Support\TimekeepingMemo::routeName('sent-preview'), $row['submission_id']) }}"
                                        >
                                            Preview
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div data-memo-client-empty class="mt-4 hidden rounded-xl border border-gray-200 bg-white px-5 py-10 text-center text-sm text-gray-500">
        No sent memos match these filters.
    </div>

    <div class="mt-4" data-memo-client-pager></div>
@endif
