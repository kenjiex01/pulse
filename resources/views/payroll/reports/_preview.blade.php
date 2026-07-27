<div class="mt-2 space-y-4 print:mt-0">
    @if (($preview['meta']['layout'] ?? null) === 'sss')
        <div class="space-y-1 text-center">
            <h3 class="text-lg font-semibold text-gray-900">{{ $preview['meta']['company_name'] ?? config('app.name') }}</h3>
            @if (! empty($preview['meta']['company_address']))
                <p class="text-sm text-gray-600">{{ $preview['meta']['company_address'] }}</p>
            @endif
            <p class="text-base font-semibold uppercase tracking-wide text-gray-900">
                Social Security System [SSS] Monthly Contribution
            </p>
            @if (! empty($preview['meta']['period_label']))
                <p class="text-sm font-medium text-gray-700">{{ $preview['meta']['period_label'] }}</p>
            @endif
        </div>

        @if (! empty($preview['meta']['batch_labels']))
            <p class="text-sm text-gray-600">
                Batches: {{ implode(' · ', $preview['meta']['batch_labels']) }}
            </p>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200 print:overflow-visible print:border-gray-400">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-gray-700" rowspan="2">SSS No.</th>
                        <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-gray-700" rowspan="2">No.</th>
                        <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-gray-700" rowspan="2">Employee</th>
                        <th class="whitespace-nowrap px-3 py-2 text-center font-semibold text-gray-700" colspan="3">Social Security</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-700" rowspan="2">EC</th>
                        <th class="whitespace-nowrap px-3 py-2 text-center font-semibold text-gray-700" colspan="2">Mandatory Provident Fund</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-700" rowspan="2">Grand Total</th>
                    </tr>
                    <tr>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-700">Employee</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-700">Employer</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-700">Total</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-700">Employee</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-700">Employer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($preview['rows'] ?? [] as $row)
                        <tr class="hover:bg-slate-50/70">
                            @foreach ($row as $index => $cell)
                                <td @class([
                                    'whitespace-nowrap px-3 py-2 text-gray-800',
                                    'text-right' => $index >= 3,
                                ])>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-gray-500">No SSS contributions found for the selected batches.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $preview['title'] ?? 'Report Preview' }}</h3>
                @if (! empty($preview['meta']['batch_labels']))
                    <p class="mt-1 text-sm text-gray-600">
                        Batches: {{ implode(' · ', $preview['meta']['batch_labels']) }}
                    </p>
                @endif
            </div>
            <p class="text-sm text-gray-500">{{ count($preview['rows'] ?? []) }} row(s)</p>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 print:overflow-visible print:border-gray-400">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach ($preview['headers'] ?? [] as $header)
                            <th class="whitespace-nowrap px-3 py-2 text-left font-semibold text-gray-700">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($preview['rows'] ?? [] as $row)
                        <tr class="hover:bg-slate-50/70">
                            @foreach ($row as $cell)
                                <td class="whitespace-nowrap px-3 py-2 text-gray-800">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ max(count($preview['headers'] ?? []), 1) }}" class="px-3 py-8 text-center text-gray-500">
                                No data to display.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
