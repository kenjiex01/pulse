@if (($preview['meta']['layout'] ?? null) === 'sss')
    <div style="text-align:center;margin:0 0 12px;">
        <div style="font-size:16px;font-weight:700;color:#111;">{{ $preview['meta']['company_name'] ?? config('app.name') }}</div>
        @if (! empty($preview['meta']['company_address']))
            <div style="font-size:12px;color:#333;">{{ $preview['meta']['company_address'] }}</div>
        @endif
        <div style="font-size:14px;font-weight:700;margin-top:6px;color:#111;text-transform:uppercase;">
            Social Security System [SSS] Monthly Contribution
        </div>
        @if (! empty($preview['meta']['period_label']))
            <div style="font-size:12px;margin-top:4px;color:#333;">{{ $preview['meta']['period_label'] }}</div>
        @endif
    </div>
    @if (! empty($preview['meta']['batch_labels']))
        <p style="font-size:11px;margin:0 0 10px;color:#555;">
            Batches: {{ implode(' · ', $preview['meta']['batch_labels']) }}
        </p>
    @endif
    <table style="width:100%;border-collapse:collapse;font-size:10px;color:#111;">
        <thead>
            <tr>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;" rowspan="2">SSS No.</th>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;" rowspan="2">No.</th>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;" rowspan="2">Employee</th>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;text-align:center;" colspan="3">Social Security</th>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;" rowspan="2">EC</th>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;text-align:center;" colspan="2">Mandatory Provident Fund</th>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;" rowspan="2">Gross Income</th>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;" rowspan="2">Grand Total</th>
            </tr>
            <tr>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;text-align:right;">Employee</th>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;text-align:right;">Employer</th>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;text-align:right;">Total</th>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;text-align:right;">Employee</th>
                <th style="border:1px solid #999;padding:5px;background:#f3f4f6;text-align:right;">Employer</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($preview['rows'] ?? [] as $row)
                <tr>
                    @foreach ($row as $index => $cell)
                        <td style="border:1px solid #ccc;padding:5px;{{ $index >= 3 ? 'text-align:right;' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="11" style="border:1px solid #ccc;padding:16px;text-align:center;color:#555;">No data to display.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@elseif (($preview['meta']['layout'] ?? null) === 'icct_per_hour')
    <div class="payroll-register-pdf-wrap">
        @include('payroll.reports._icct-payroll-register-table', ['preview' => $preview, 'forPdf' => true])
    </div>
@elseif (($preview['meta']['layout'] ?? null) === 'bir_tax')
    @include('payroll.reports._bir-tax-withheld-table', ['preview' => $preview, 'forPdf' => true])
@elseif (in_array($preview['meta']['layout'] ?? null, ['philhealth', 'pagibig'], true))
    @include('payroll.reports._government-contribution-table', ['preview' => $preview, 'forPdf' => true])
@elseif (($preview['meta']['layout'] ?? null) === 'payslip')
    @include('payroll.reports._payslip-document', ['preview' => $preview, 'forPdf' => true])
@elseif (($preview['meta']['layout'] ?? null) === 'bir_1601c')
    @include('payroll.reports._bir-1601c-document', ['preview' => $preview, 'forPdf' => true])
@elseif (($preview['meta']['layout'] ?? null) === 'bir_2316')
    @include('payroll.reports._bir-2316-document', ['preview' => $preview, 'forPdf' => true])
@elseif (($preview['meta']['layout'] ?? null) === 'attendance_view' && ! empty($preview['meta']['sections']))
    @include('payroll.reports._attendance-view-document', ['preview' => $preview, 'forPdf' => true])
@else
        <h1 style="font-size:18px;font-weight:700;margin:0 0 4px;color:#111;">{{ $preview['title'] ?? 'Report Preview' }}</h1>
        @if (! empty($preview['meta']['filter_summary']))
            <p style="font-size:12px;margin:0 0 12px;color:#333;">{{ $preview['meta']['filter_summary'] }}</p>
        @endif
        @if (! empty($preview['meta']['batch_labels']))
        <p style="font-size:12px;margin:0 0 12px;color:#333;">
            Batches: {{ implode(' · ', $preview['meta']['batch_labels']) }}
        </p>
    @endif
    <p style="font-size:12px;margin:0 0 12px;color:#555;">{{ count($preview['rows'] ?? []) }} row(s)</p>

    <table style="width:100%;border-collapse:collapse;font-size:11px;color:#111;">
        <thead>
            <tr>
                @foreach ($preview['headers'] ?? [] as $header)
                    <th style="border:1px solid #999;padding:6px 8px;text-align:left;background:#f3f4f6;color:#111;font-weight:600;">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($preview['rows'] ?? [] as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td style="border:1px solid #ccc;padding:6px 8px;color:#111;background:#fff;">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($preview['headers'] ?? []), 1) }}" style="border:1px solid #ccc;padding:16px;text-align:center;color:#555;">
                        No data to display.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endif
