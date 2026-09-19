@php
    $summary = ($sendSummary ?? collect())->take(100);
    $totalSends = $summary->sum(fn ($row) => (int) ($row->send_count ?? 0));
@endphp

<div class="border-t border-gray-100 pt-4" data-company-document-send-history>
    <div class="mb-2 flex items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-gray-900">Send history</h3>
        <span class="text-xs text-gray-500">{{ number_format($summary->count()) }} employee(s) · {{ number_format($totalSends) }} send(s)</span>
    </div>

    @if ($summary->isEmpty())
        <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
            No sends yet for this document.
        </p>
    @else
        <div class="max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="sticky top-0 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2 font-medium">Employee</th>
                        <th class="px-3 py-2 font-medium">Times sent</th>
                        <th class="px-3 py-2 font-medium">Sent on</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($summary as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2">
                                <p class="font-medium text-gray-900">{{ $row->employee?->employee_number ?: '—' }}</p>
                                <p class="text-xs text-gray-600">{{ $row->employee?->full_name ?: '—' }}</p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <span class="inline-flex items-center rounded-full bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-800">
                                    {{ number_format((int) ($row->send_count ?? 0)) }}×
                                </span>
                            </td>
                            <td class="px-3 py-2 text-gray-600">
                                <ul class="space-y-1 text-xs">
                                    @foreach ($row->sent_at_list ?? [] as $sentAt)
                                        <li>{{ $sentAt?->format('M j, Y g:i A') ?: '—' }}</li>
                                    @endforeach
                                </ul>
                                @if ($row->last_sender?->name)
                                    <p class="mt-1 text-[11px] text-gray-400">Last by {{ $row->last_sender->name }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-gray-500">Grouped per employee. Re-sending adds to the count and sent dates list.</p>
    @endif
</div>
