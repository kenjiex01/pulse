@php
    $nteCases = $nteCases ?? collect();
    $nteSyncConfigured = $nteSyncConfigured ?? false;
@endphp

@if ($form->expects_web_nte_response || $nteCases->isNotEmpty())
    <div class="mt-6 border-t border-gray-200 pt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Web NTE response tracking</h4>
            @can('update', $form)
                <form method="POST" action="{{ route('company-documents.nte-cases.sync', $form) }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="btn-secondary !px-2 !py-1 text-[11px]"
                        @disabled(! $nteSyncConfigured)
                        title="{{ $nteSyncConfigured ? 'Pull NTE responses from Skolaris (same Company Documents template code)' : 'Set Skolaris Pulse API key and enable web NTE on this template' }}"
                    >
                        Pull from Skolaris web
                    </button>
                </form>
            @endcan
        </div>

        @if ($form->expects_web_nte_response)
            <p class="mt-2 text-xs text-gray-600">
                Employees have {{ $form->nteResponseDays() }} calendar day(s) to submit on Skolaris after send.
            </p>
        @endif

        @if ($nteCases->isEmpty())
            <p class="mt-3 text-xs text-gray-500">No NTE cases recorded for this template yet.</p>
        @else
            <div class="mt-3 max-h-64 overflow-y-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-2">Employee</th>
                            <th class="px-3 py-2">Sent</th>
                            <th class="px-3 py-2">Due</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($nteCases as $case)
                            <tr>
                                <td class="px-3 py-2 text-gray-900">
                                    {{ $case->employee?->full_name ?? '—' }}
                                    @if ($case->employee?->employee_number)
                                        <span class="block font-mono text-[10px] text-gray-400">{{ $case->employee->employee_number }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ $case->sent_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $case->due_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @php
                                        $statusClass = match ($case->status) {
                                            \App\Models\CompanyDocumentNteCase::STATUS_RECEIVED => 'bg-green-50 text-green-800',
                                            \App\Models\CompanyDocumentNteCase::STATUS_OVERDUE => 'bg-red-50 text-red-800',
                                            \App\Models\CompanyDocumentNteCase::STATUS_CANCELLED => 'bg-gray-100 text-gray-600',
                                            default => 'bg-amber-50 text-amber-800',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $statusClass }}">
                                        {{ ucfirst($case->status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    @can('update', $form)
                                        @if ($case->isOpen())
                                            <form
                                                method="POST"
                                                action="{{ route('company-documents.nte-cases.mark-received', [$form, $case]) }}"
                                                class="inline"
                                                onsubmit="return confirm('Mark this NTE as received manually?')"
                                            >
                                                @csrf
                                                <button type="submit" class="text-[11px] font-medium text-[#0B318F] hover:underline">
                                                    Mark received
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
