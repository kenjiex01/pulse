@php
    $meta = $preview['meta'] ?? [];
    $lines = $meta['employee_lines'] ?? [];
    $totals = $meta['totals'] ?? [];
    $forPdf = $forPdf ?? false;
    $cell = $forPdf
        ? 'border:1px solid #999;padding:5px;font-size:10px;'
        : null;
    $th = $forPdf
        ? 'border:1px solid #999;padding:5px;background:#f3f4f6;font-size:10px;font-weight:700;text-align:left;'
        : null;
@endphp

<div @class(['space-y-4' => ! $forPdf]) style="{{ $forPdf ? 'font-size:11px;color:#111;' : '' }}">
    <div style="text-align:center;{{ $forPdf ? 'margin-bottom:12px;' : '' }}">
        <div @class(['text-xs text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:10px;color:#444;' : '' }}">
            Republic of the Philippines<br>
            Department of Finance<br>
            Bureau of Internal Revenue
        </div>
        <div @class(['mt-2 text-base font-bold text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'margin-top:6px;font-size:14px;font-weight:700;' : '' }}">
            BIR Form No. {{ $meta['form_number'] ?? '1601-C' }}
        </div>
        <div @class(['text-sm font-semibold uppercase text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'font-size:11px;font-weight:700;text-transform:uppercase;' : '' }}">
            {{ $meta['form_title'] ?? 'Monthly Remittance Return of Income Taxes Withheld on Compensation' }}
        </div>
    </div>

    <table @class(['w-full text-sm' => ! $forPdf]) style="{{ $forPdf ? 'width:100%;border-collapse:collapse;font-size:10px;margin-bottom:10px;' : 'width:100%;' }}">
        <tbody>
            <tr>
                <td @class(['py-1 pr-3 font-medium text-gray-600 w-48' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 8px 3px 0;width:180px;font-weight:600;' : '' }}">For the Month of</td>
                <td @class(['py-1 text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 0;' : '' }}">{{ $meta['month_of_return'] ?? '' }}</td>
            </tr>
            <tr>
                <td @class(['py-1 pr-3 font-medium text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 8px 3px 0;font-weight:600;' : '' }}">Withholding Agent</td>
                <td @class(['py-1 text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 0;' : '' }}">{{ $meta['company_name'] ?? '' }}</td>
            </tr>
            <tr>
                <td @class(['py-1 pr-3 font-medium text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 8px 3px 0;font-weight:600;' : '' }}">TIN</td>
                <td @class(['py-1 text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 0;' : '' }}">{{ $meta['company_tin_formatted'] ?: ($meta['company_tin'] ?? '') }}</td>
            </tr>
            <tr>
                <td @class(['py-1 pr-3 font-medium text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 8px 3px 0;font-weight:600;' : '' }}">Registered Address</td>
                <td @class(['py-1 text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 0;' : '' }}">{{ $meta['company_address'] ?? '' }}</td>
            </tr>
            <tr>
                <td @class(['py-1 pr-3 font-medium text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 8px 3px 0;font-weight:600;' : '' }}">RDO Code</td>
                <td @class(['py-1 text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 0;' : '' }}">{{ $meta['company_rdo_code'] ?? '' }}</td>
            </tr>
            <tr>
                <td @class(['py-1 pr-3 font-medium text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 8px 3px 0;font-weight:600;' : '' }}">Payroll Batch</td>
                <td @class(['py-1 text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'padding:3px 0;' : '' }}">{{ $meta['batch_label'] ?? '' }}</td>
            </tr>
        </tbody>
    </table>

    <div @class(['overflow-x-auto rounded-xl border border-gray-200' => ! $forPdf])>
        <table @class(['min-w-full divide-y divide-gray-200 text-sm' => ! $forPdf]) style="{{ $forPdf ? 'width:100%;border-collapse:collapse;' : '' }}">
            <thead @class(['bg-slate-50' => ! $forPdf])>
                <tr>
                    <th @class(['px-3 py-2 text-left font-semibold text-gray-700' => ! $forPdf]) style="{{ $th }}">Seq</th>
                    <th @class(['px-3 py-2 text-left font-semibold text-gray-700' => ! $forPdf]) style="{{ $th }}">TIN</th>
                    <th @class(['px-3 py-2 text-left font-semibold text-gray-700' => ! $forPdf]) style="{{ $th }}">Name of Payee</th>
                    <th @class(['px-3 py-2 text-left font-semibold text-gray-700' => ! $forPdf]) style="{{ $th }}">ATC</th>
                    <th @class(['px-3 py-2 text-right font-semibold text-gray-700' => ! $forPdf]) style="{{ $th }}{{ $forPdf ? 'text-align:right;' : '' }}">Tax Base / Compensation</th>
                    <th @class(['px-3 py-2 text-right font-semibold text-gray-700' => ! $forPdf]) style="{{ $th }}{{ $forPdf ? 'text-align:right;' : '' }}">Tax Withheld</th>
                </tr>
            </thead>
            <tbody @class(['divide-y divide-gray-100 bg-white' => ! $forPdf])>
                @forelse ($lines as $index => $line)
                    <tr>
                        <td @class(['px-3 py-2 text-gray-800' => ! $forPdf]) style="{{ $cell }}">{{ $index + 1 }}</td>
                        <td @class(['px-3 py-2 text-gray-800' => ! $forPdf]) style="{{ $cell }}">{{ $line['tin_formatted'] !== '' ? $line['tin_formatted'] : $line['tin'] }}</td>
                        <td @class(['px-3 py-2 text-gray-800' => ! $forPdf]) style="{{ $cell }}">{{ $line['employee_name'] }}</td>
                        <td @class(['px-3 py-2 text-gray-800' => ! $forPdf]) style="{{ $cell }}">{{ $meta['compensation_atc'] ?? 'WI010' }}</td>
                        <td @class(['px-3 py-2 text-right text-gray-800' => ! $forPdf]) style="{{ $cell }}{{ $forPdf ? 'text-align:right;' : '' }}">{{ number_format((float) $line['taxable_compensation'], 2) }}</td>
                        <td @class(['px-3 py-2 text-right text-gray-800' => ! $forPdf]) style="{{ $cell }}{{ $forPdf ? 'text-align:right;' : '' }}">{{ number_format((float) $line['tax_withheld'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" @class(['px-3 py-8 text-center text-gray-500' => ! $forPdf]) style="{{ $cell }}{{ $forPdf ? 'text-align:center;padding:16px;' : '' }}">
                            No employees selected or no compensation found in the posted batch.
                        </td>
                    </tr>
                @endforelse
                @if ($lines !== [])
                    <tr @class(['bg-slate-50 font-semibold' => ! $forPdf])>
                        <td colspan="4" @class(['px-3 py-2 text-right text-gray-800' => ! $forPdf]) style="{{ $cell }}{{ $forPdf ? 'text-align:right;font-weight:700;' : '' }}">TOTAL</td>
                        <td @class(['px-3 py-2 text-right text-gray-900' => ! $forPdf]) style="{{ $cell }}{{ $forPdf ? 'text-align:right;font-weight:700;' : '' }}">{{ number_format((float) ($totals['taxable_compensation'] ?? 0), 2) }}</td>
                        <td @class(['px-3 py-2 text-right text-gray-900' => ! $forPdf]) style="{{ $cell }}{{ $forPdf ? 'text-align:right;font-weight:700;' : '' }}">{{ number_format((float) ($totals['tax_withheld'] ?? 0), 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div @class(['grid grid-cols-1 gap-6 pt-4 sm:grid-cols-2' => ! $forPdf]) style="{{ $forPdf ? 'margin-top:18px;' : '' }}">
        <div>
            <p @class(['text-xs font-medium uppercase tracking-wide text-gray-500' => ! $forPdf]) style="{{ $forPdf ? 'font-size:9px;color:#666;text-transform:uppercase;' : '' }}">Authorized Signatory</p>
            <p @class(['mt-6 border-b border-gray-400 pb-1 text-sm font-semibold text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'margin-top:28px;border-bottom:1px solid #666;padding-bottom:2px;font-weight:700;' : '' }}">
                {{ $meta['signatory_name'] ?? '' }}
            </p>
            <p @class(['mt-1 text-xs text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:10px;color:#555;' : '' }}">{{ $meta['signatory_title'] ?? '' }}</p>
        </div>
    </div>

    @if (! empty($meta['disclaimer']))
        <p @class(['text-xs text-gray-500' => ! $forPdf]) style="{{ $forPdf ? 'margin-top:12px;font-size:8px;color:#666;' : '' }}">{{ $meta['disclaimer'] }}</p>
    @endif
</div>
