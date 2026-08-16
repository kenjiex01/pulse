@php
    $headers = $preview['headers'] ?? [];
    $subheaders = $preview['meta']['subheaders'] ?? [];
    $highlightIndices = $preview['meta']['highlight_indices'] ?? [];
    $hasSubheaders = collect($subheaders)->contains(fn ($label) => filled($label));
    $forPdf = $forPdf ?? false;
@endphp

<div @class(['space-y-1 text-center' => ! $forPdf]) style="{{ $forPdf ? 'text-align:center;margin:0 0 6px;' : '' }}">
    <h3 @class(['text-lg font-semibold text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'font-size:11px;font-weight:700;margin:0;color:#111;' : '' }}">
        {{ $preview['meta']['company_name'] ?? config('app.name') }}
    </h3>
    <p @class(['text-base font-semibold uppercase tracking-wide text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'font-size:9px;font-weight:700;margin:2px 0 0;color:#111;text-transform:uppercase;' : '' }}">
        {{ $preview['meta']['subtitle'] ?? 'PAYROLL REGISTER' }}
    </p>
    @if (! empty($preview['meta']['period_label']))
        <p @class(['text-sm font-medium text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'font-size:7px;margin:2px 0 0;color:#333;' : '' }}">
            Period Covered: {{ $preview['meta']['period_label'] }}
        </p>
    @endif
</div>

@if (! empty($preview['meta']['batch_labels']))
    <p @class(['text-sm text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:6.5px;margin:4px 0;color:#555;' : '' }}">
        Batches: {{ implode(' · ', $preview['meta']['batch_labels']) }}
    </p>
@endif

<p @class(['text-sm text-gray-500' => ! $forPdf]) style="{{ $forPdf ? 'font-size:6.5px;margin:0 0 4px;color:#555;' : '' }}">
    {{ count($preview['rows'] ?? []) }} row(s)
</p>

<div @class(['overflow-x-auto rounded-xl border border-gray-200 print:overflow-visible print:border-gray-400' => ! $forPdf])>
    <table @class(['min-w-full divide-y divide-gray-200 text-xs' => ! $forPdf]) style="{{ $forPdf ? 'border-collapse:collapse;font-size:5.5px;line-height:1.15;color:#111;' : '' }}">
        <thead @class(['bg-slate-50' => ! $forPdf])>
            <tr>
                @foreach ($headers as $index => $header)
                    <th
                        @class([
                            'whitespace-nowrap px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf,
                            'bg-yellow-200' => ! $forPdf && in_array($index, $highlightIndices, true),
                        ])
                        style="{{ $forPdf ? 'border:0.4px solid #999;padding:1px 2px;background:' . (in_array($index, $highlightIndices, true) ? '#ffff00' : '#f3f4f6') . ';font-weight:700;text-align:center;white-space:nowrap;' : '' }}"
                    >{{ $header }}</th>
                @endforeach
            </tr>
            @if ($hasSubheaders)
                <tr>
                    @foreach ($subheaders as $index => $subheader)
                        <th
                            @class([
                                'whitespace-nowrap px-2 py-1 text-center text-[11px] font-medium text-gray-600' => ! $forPdf,
                                'bg-yellow-200' => ! $forPdf && in_array($index, $highlightIndices, true),
                            ])
                            style="{{ $forPdf ? 'border:0.4px solid #999;padding:1px 2px;background:' . (in_array($index, $highlightIndices, true) ? '#ffff00' : '#f3f4f6') . ';font-weight:600;text-align:center;white-space:nowrap;' : '' }}"
                        >{{ $subheader }}</th>
                    @endforeach
                </tr>
            @endif
        </thead>
        <tbody @class(['divide-y divide-gray-100 bg-white' => ! $forPdf])>
            @forelse ($preview['rows'] ?? [] as $row)
                <tr @class(['hover:bg-slate-50/70' => ! $forPdf])>
                    @foreach ($row as $index => $cell)
                        <td
                            @class([
                                'whitespace-nowrap px-2 py-1.5 text-gray-800' => ! $forPdf,
                                'bg-yellow-50' => ! $forPdf && in_array($index, $highlightIndices, true),
                            ])
                            style="{{ $forPdf ? 'border:0.4px solid #ccc;padding:1px 2px;background:' . (in_array($index, $highlightIndices, true) ? '#ffffcc' : '#fff') . ';white-space:nowrap;' : '' }}"
                        >{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td
                        colspan="{{ max(count($headers), 1) }}"
                        @class(['px-3 py-8 text-center text-gray-500' => ! $forPdf])
                        style="{{ $forPdf ? 'border:0.4px solid #ccc;padding:8px;text-align:center;color:#555;' : '' }}"
                    >
                        No data to display.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
