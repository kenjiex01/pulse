@php
    /** @var \App\Services\EmployeeHistoryService $historyService */
@endphp

<div class="space-y-4" data-employee-profile-lazy-content>
    <div>
        <h2 class="text-lg font-semibold text-gray-900">History Details</h2>
        <p class="mt-1 text-sm text-gray-500">
            Change log for this employee. Each update stores the previous data snapshot from system logs.
        </p>
    </div>

    @if ($logs->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
            No change history recorded yet for this employee.
        </div>
    @else
        <div class="space-y-3">
            @foreach ($logs as $log)
                @php
                    $changes = $historyService->changesForLog($log);
                    $actionLabel = $historyService->actionLabel($log->action);
                    $actionClass = match ($log->action) {
                        'create' => 'bg-green-100 text-green-800',
                        'update' => 'bg-blue-100 text-blue-800',
                        'delete' => 'bg-red-100 text-red-800',
                        default => 'bg-gray-100 text-gray-800',
                    };
                @endphp

                <details class="rounded-lg border border-gray-200 bg-white">
                    <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $actionClass }}">
                                    {{ $actionLabel }}
                                </span>
                                <span class="text-sm font-medium text-gray-900">
                                    {{ $log->created_at?->format('M j, Y g:i A') ?? '—' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600">{{ $log->description ?? 'Employee record change' }}</p>
                        </div>
                        <div class="text-right text-xs text-gray-500">
                            <p>{{ $log->user?->name ?? 'System' }}</p>
                            <p>{{ count($changes) }} field(s)</p>
                        </div>
                    </summary>

                    <div class="border-t border-gray-100 px-4 py-3">
                        @if ($changes === [])
                            <p class="text-sm text-gray-500">No field-level snapshot stored for this entry.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="table-skolaris min-w-full text-sm">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-2 text-left">Field</th>
                                            <th class="px-3 py-2 text-left">Previous Value</th>
                                            <th class="px-3 py-2 text-left">New Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($changes as $change)
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-gray-900">{{ $change['label'] }}</td>
                                                <td class="px-3 py-2 align-top text-gray-600">
                                                    @if (is_array($change['old']))
                                                        <pre class="max-h-48 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-2 text-xs">{{ $historyService->formatDisplayValue($change['old']) }}</pre>
                                                    @else
                                                        {{ $historyService->formatDisplayValue($change['old']) }}
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 align-top text-gray-900">
                                                    @if (is_array($change['new']))
                                                        <pre class="max-h-48 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-2 text-xs">{{ $historyService->formatDisplayValue($change['new']) }}</pre>
                                                    @else
                                                        {{ $historyService->formatDisplayValue($change['new']) }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>

        @if ($logs->hasPages())
            @include('partials.data-table-pagination', ['paginator' => $logs])
        @endif
    @endif
</div>
