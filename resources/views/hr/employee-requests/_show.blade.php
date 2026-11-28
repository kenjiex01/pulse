@php
    $matcher = app(\App\Services\EmployeeRequestPullService::class);
    $preparedBy = $matcher->matchEmployee($request->prepared_by_employee_number, $request->prepared_by_name);
    $includedBatches = $request->payrollInclusions
        ->filter(fn ($row) => $row->included_at !== null && $row->payrollBatch !== null)
        ->map(fn ($row) => $row->payrollBatch->formattedBatchNo())
        ->unique()
        ->values();
@endphp
<div class="space-y-4 text-sm text-gray-800">
    <p>{{ $request->apply_summary }}</p>
    @if ($includedBatches->isNotEmpty())
        <p class="text-gray-600">Included in payroll batch {{ $includedBatches->join(', ') }}.</p>
    @else
        <p class="text-gray-600">Not in a payroll batch yet. Process the employee's current batch to include it. A request approved this cutoff is paid here even when the work date is from an earlier month, and it is not paid again after that.</p>
    @endif
    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500">Prepared by</dt>
            <dd>
                {{ $request->prepared_by_name }}
                @if ($preparedBy)
                    <a href="{{ route('employees.show', $preparedBy) }}" class="text-[#0B318F] hover:underline">{{ $preparedBy->employee_number }}</a>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500">Department</dt>
            <dd>{{ $request->department ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500">Campus</dt>
            <dd>{{ $request->campus_name ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500">Approved</dt>
            <dd>{{ $request->approved_at?->format('M j, Y g:i A') ?: '—' }}</dd>
        </div>
    </dl>

    @if ($request->request_type_code === 'amend_attendance')
        @php $payload = $request->form_payload ?? []; @endphp
        <div>
            <h3 class="mb-1 font-semibold text-[#0B318F]">Amended logs</h3>
            <p>Date: {{ $payload['attendance_date'] ?? '—' }}</p>
            <p>In AM: {{ $payload['time_in_am'] ?? '—' }} · Out noon: {{ $payload['time_out_nn'] ?? '—' }}</p>
            <p>In noon: {{ $payload['time_in_nn'] ?? '—' }} · Out PM: {{ $payload['time_out_pm'] ?? '—' }}</p>
            @if (! empty($payload['reason']))
                <p class="mt-1 text-gray-600">{{ $payload['reason'] }}</p>
            @endif
        </div>
    @endif

    @if ($request->request_type_code === 'job_order')
        <div>
            <h3 class="mb-1 font-semibold text-[#0B318F]">Work details</h3>
            @forelse ($request->items ?? [] as $item)
                @php
                    $matched = $matcher->matchEmployee($item['employee_id'] ?? null, $item['employee_name'] ?? null);
                @endphp
                <div class="mb-2 rounded-lg border border-gray-200 p-3">
                    <p class="font-medium">{{ $item['employee_name'] ?? 'Employee' }}</p>
                    @if ($matched)
                        <p class="text-xs text-gray-600">
                            People360:
                            <a href="{{ route('employees.show', $matched) }}" class="font-medium text-[#0B318F] hover:underline">{{ $matched->employee_number }}</a>
                            · {{ $matched->full_name }}
                        </p>
                    @else
                        <p class="text-xs text-amber-700">No matching People360 employee.</p>
                    @endif
                    <p class="mt-1 whitespace-pre-line text-gray-600">{{ $item['description_of_works'] ?? '' }}</p>
                    <ul class="mt-2 space-y-1 text-xs text-gray-700">
                        @foreach ($item['work_schedules'] ?? [] as $schedule)
                            @php
                                $from = $schedule['date_from'] ?? null;
                                $to = $schedule['date_to'] ?? null;
                                $fromLabel = $from ? \Carbon\CarbonImmutable::parse($from)->format('M j, Y') : '—';
                                $toLabel = $to ? \Carbon\CarbonImmutable::parse($to)->format('M j, Y') : null;
                                $timeFrom = $schedule['time_from'] ?? '';
                                $timeTo = $schedule['time_to'] ?? '';
                                $timeFromLabel = $timeFrom !== '' ? \Carbon\CarbonImmutable::parse($timeFrom)->format('g:i A') : '—';
                                $timeToLabel = $timeTo !== '' ? \Carbon\CarbonImmutable::parse($timeTo)->format('g:i A') : '—';
                            @endphp
                            <li>
                                <span class="font-medium">Date:</span>
                                {{ $fromLabel }}@if ($toLabel && $toLabel !== $fromLabel) – {{ $toLabel }}@endif
                                <span class="ml-2 font-medium">Time:</span>
                                {{ $timeFromLabel }} – {{ $timeToLabel }}
                                @if (! empty($schedule['break_minutes']))
                                    <span class="text-gray-500">· Break {{ $schedule['break_minutes'] }} mins</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="text-gray-500">No work rows.</p>
            @endforelse
        </div>
    @endif
</div>
