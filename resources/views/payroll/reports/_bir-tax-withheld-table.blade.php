@php
    $forPdf = $forPdf ?? false;
    $headers = $preview['headers'] ?? [];
    $subheaders = $preview['meta']['subheaders'] ?? [];
    $totals = $preview['meta']['totals'] ?? [];
@endphp

<div @class(['space-y-1 text-center' => ! $forPdf]) style="{{ $forPdf ? 'text-align:center;margin:0 0 12px;' : '' }}">
    <h3 @class(['text-lg font-semibold text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'font-size:14px;font-weight:700;margin:0;' : '' }}">
        {{ $preview['meta']['company_name'] ?? config('app.name') }}
    </h3>
    @if (! empty($preview['meta']['company_address']))
        <p @class(['text-sm text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:10px;margin:2px 0 0;color:#333;' : '' }}">
            {{ $preview['meta']['company_address'] }}
        </p>
    @endif
    <p @class(['text-base font-semibold uppercase tracking-wide text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'font-size:12px;font-weight:700;margin:6px 0 0;text-transform:uppercase;' : '' }}">
        {{ $preview['meta']['title_line'] ?? "EMPLOYEES' TAX WITHHELD" }}
    </p>
    @if (! empty($preview['meta']['period_label']))
        <p @class(['text-sm font-medium text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'font-size:10px;margin:4px 0 0;color:#333;' : '' }}">
            {{ $preview['meta']['period_label'] }}
        </p>
    @endif
</div>

@if (! empty($preview['meta']['batch_labels']))
    <p @class(['text-sm text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:9px;margin:0 0 8px;color:#555;' : '' }}">
        Batches: {{ implode(' · ', $preview['meta']['batch_labels']) }}
    </p>
@endif

<div @class(['overflow-x-auto rounded-xl border border-gray-200 print:overflow-visible' => ! $forPdf])>
    <table @class(['min-w-full divide-y divide-gray-200 text-xs' => ! $forPdf]) style="{{ $forPdf ? 'width:100%;border-collapse:collapse;font-size:9px;color:#111;' : '' }}">
        <thead @class(['bg-slate-50' => ! $forPdf])>
            <tr>
                @foreach ($headers as $index => $header)
                    <th
                        @class([
                            'whitespace-nowrap px-2 py-2 font-semibold text-gray-700' => ! $forPdf,
                            'text-left' => ! $forPdf && $index < 2,
                            'text-right' => ! $forPdf && $index >= 2,
                        ])
                        style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;font-weight:700;text-align:'.($index < 2 ? 'left' : 'right').';' : '' }}"
                    >{{ $header }}</th>
                @endforeach
            </tr>
            @if (collect($subheaders)->contains(fn ($label) => filled($label)))
                <tr>
                    @foreach ($subheaders as $index => $subheader)
                        <th
                            @class([
                                'whitespace-nowrap px-2 py-1 text-[11px] font-medium text-gray-600' => ! $forPdf,
                                'text-left' => ! $forPdf && $index < 2,
                                'text-right' => ! $forPdf && $index >= 2,
                            ])
                            style="{{ $forPdf ? 'border:1px solid #999;padding:3px;background:#f3f4f6;text-align:'.($index < 2 ? 'left' : 'right').';' : '' }}"
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
                                'text-left' => ! $forPdf && $index < 2,
                                'text-right' => ! $forPdf && $index >= 2,
                            ])
                            style="{{ $forPdf ? 'border:1px solid #ccc;padding:3px;text-align:'.($index < 2 ? 'left' : 'right').';' : '' }}"
                        >{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td
                        colspan="{{ max(count($headers), 1) }}"
                        @class(['px-3 py-8 text-center text-gray-500' => ! $forPdf])
                        style="{{ $forPdf ? 'border:1px solid #ccc;padding:12px;text-align:center;color:#555;' : '' }}"
                    >
                        No tax withheld data found for the selected batches.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (! empty($preview['rows']))
            <tfoot>
                <tr @class(['font-semibold bg-slate-50' => ! $forPdf])>
                    <td colspan="2" @class(['px-2 py-2' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;font-weight:700;' : '' }}">TOTAL</td>
                    @foreach ([
                        'non_taxable_overtime',
                        'mwe_income',
                        'taxable_no_wt',
                        'taxable_with_wt',
                        'tax_withheld',
                        'deminimis_benefit',
                        'gross_income',
                    ] as $key)
                        <td
                            @class(['px-2 py-2 text-right' => ! $forPdf])
                            style="{{ $forPdf ? 'border:1px solid #999;padding:4px;text-align:right;font-weight:700;' : '' }}"
                        >
                            {{ ($totals[$key] ?? 0) > 0 ? number_format((float) $totals[$key], 2) : '' }}
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</div>
