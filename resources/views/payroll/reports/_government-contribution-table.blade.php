@php
    $forPdf = $forPdf ?? false;
    $layout = $preview['meta']['layout'] ?? '';
    $employeeRows = $preview['meta']['employee_rows'] ?? [];
    $sections = $preview['meta']['sections'] ?? [];
    $totals = $preview['meta']['grand_totals'] ?? ($preview['meta']['totals'] ?? []);
    $isPhilhealth = $layout === 'philhealth';
    $isPagibig = $layout === 'pagibig';
    $rowCount = $isPagibig && $sections !== []
        ? collect($sections)->sum(fn ($section) => count($section['employee_rows'] ?? []))
        : count($employeeRows);
@endphp

<div @class(['space-y-1 text-center' => ! $forPdf]) style="{{ $forPdf ? 'text-align:center;margin:0 0 12px;' : '' }}">
    <h3 @class(['text-lg font-semibold text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'font-size:16px;font-weight:700;margin:0;color:#111;' : '' }}">
        {{ $preview['meta']['company_name'] ?? config('app.name') }}
    </h3>
    @if (! empty($preview['meta']['company_address']))
        <p @class(['text-sm text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:11px;margin:2px 0 0;color:#333;' : '' }}">
            {{ $preview['meta']['company_address'] }}
        </p>
    @endif
    <p @class(['text-base font-semibold uppercase tracking-wide text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'font-size:13px;font-weight:700;margin:6px 0 0;color:#111;text-transform:uppercase;' : '' }}">
        {{ $isPhilhealth ? config('government_contribution_reports.philhealth.title_line_3') : config('government_contribution_reports.pagibig.title_line_3') }}
    </p>
    @if ($isPhilhealth)
        <p @class(['text-sm font-semibold uppercase text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'font-size:12px;font-weight:700;margin:2px 0 0;color:#111;text-transform:uppercase;' : '' }}">
            {{ config('government_contribution_reports.philhealth.title_line_4') }}
        </p>
    @else
        <p @class(['text-sm text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'font-size:11px;margin:2px 0 0;color:#333;' : '' }}">
            SSS# {{ config('government_contribution_reports.pagibig_employer_sss') }}
        </p>
    @endif
    @if (! empty($preview['meta']['period_label']))
        <p @class(['text-sm font-medium text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'font-size:11px;margin:4px 0 0;color:#333;' : '' }}">
            {{ $preview['meta']['period_label'] }}
        </p>
    @endif
</div>

@if (! empty($preview['meta']['batch_labels']))
    <p @class(['text-sm text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:10px;margin:8px 0;color:#555;' : '' }}">
        Batches: {{ implode(' · ', $preview['meta']['batch_labels']) }}
    </p>
@endif

<p @class(['text-sm text-gray-500' => ! $forPdf]) style="{{ $forPdf ? 'font-size:10px;margin:0 0 8px;color:#555;' : '' }}">
    {{ $rowCount }} row(s)
</p>

<div @class(['overflow-x-auto rounded-xl border border-gray-200 print:overflow-visible print:border-gray-400' => ! $forPdf])>
    <table @class(['min-w-full divide-y divide-gray-200 text-xs' => ! $forPdf]) style="{{ $forPdf ? 'width:100%;border-collapse:collapse;font-size:8px;color:#111;' : '' }}">
        <thead @class(['bg-slate-50' => ! $forPdf])>
            @if ($isPhilhealth)
                <tr>
                    <th rowspan="2" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">No.</th>
                    <th colspan="3" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">STAFF</th>
                    <th colspan="2" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">PHIL. HEALTH INSURANCE</th>
                    <th colspan="2" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}"></th>
                    <th rowspan="2" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">Total Contribution</th>
                    <th rowspan="2" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">Gross</th>
                    <th rowspan="2" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">PhilHealth No.</th>
                </tr>
                <tr>
                    @foreach (['Last Name', 'First Name', 'Middle Name', 'Employee', 'Employer', 'Employee', 'Employer'] as $label)
                        <th @class(['px-2 py-1 text-center font-medium text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">{{ $label }}</th>
                    @endforeach
                </tr>
            @else
                <tr>
                    <th rowspan="2" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">Birthdate</th>
                    <th rowspan="2" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">Pag-IBIG No.</th>
                    <th rowspan="2" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">TIN</th>
                    <th colspan="4" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}"></th>
                    <th colspan="3" @class(['px-2 py-2 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">Monthly Contribution</th>
                </tr>
                <tr>
                    @foreach (['No.', 'Last Name', 'First Name', 'Middle Name', 'Employee', 'Employer', 'Total'] as $label)
                        <th @class(['px-2 py-1 text-center font-medium text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">{{ $label }}</th>
                    @endforeach
                </tr>
            @endif
        </thead>
        <tbody @class(['divide-y divide-gray-100 bg-white' => ! $forPdf])>
            @if ($isPagibig && $sections !== [])
                @foreach ($sections as $sectionIndex => $section)
                    @if ($sectionIndex > 0)
                        <tr @class(['bg-slate-100' => ! $forPdf])>
                            <td colspan="3" @class(['px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                            <td @class(['px-2 py-1.5 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;font-weight:600;' : '' }}">No.</td>
                            <td @class(['px-2 py-1.5 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;font-weight:600;' : '' }}">{{ $section['label'] ?? '' }}</td>
                            <td colspan="2" @class(['px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                            <td @class(['px-2 py-1.5 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;font-weight:600;' : '' }}">Employee</td>
                            <td @class(['px-2 py-1.5 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;font-weight:600;' : '' }}">Employer</td>
                            <td @class(['px-2 py-1.5 text-center font-semibold text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;font-weight:600;' : '' }}">Total</td>
                        </tr>
                    @endif

                    @foreach ($section['employee_rows'] ?? [] as $row)
                        <tr>
                            @foreach ([
                                $row['birthdate'],
                                $row['pagibig_number'],
                                $row['tin_number'],
                                $row['no'],
                                $row['last_name'],
                                $row['first_name'],
                                $row['middle_name'],
                                number_format($row['employee_share'], 2),
                                number_format($row['employer_share'], 2),
                                number_format($row['total_contribution'], 2),
                            ] as $index => $cell)
                                <td @class(['whitespace-nowrap px-2 py-1.5 text-gray-800', 'text-right' => $index >= 7]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' . ($index >= 7 ? 'text-align:right;' : '') : '' }}">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr @class(['bg-slate-50 font-semibold' => ! $forPdf])>
                        <td colspan="7" @class(['px-2 py-1.5 text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}">Sub-total</td>
                        <td @class(['px-2 py-1.5 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($section['totals']['employee_share'] ?? 0, 2) }}</td>
                        <td @class(['px-2 py-1.5 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($section['totals']['employer_share'] ?? 0, 2) }}</td>
                        <td @class(['px-2 py-1.5 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($section['totals']['total_contribution'] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            @elseif ($isPhilhealth)
                @forelse ($employeeRows as $row)
                <tr>
                    @if ($isPhilhealth)
                        @foreach ([
                            $row['no'],
                            $row['last_name'],
                            $row['first_name'],
                            $row['middle_name'],
                            number_format($row['employee_share'], 2),
                            number_format($row['employer_share'], 2),
                            '-',
                            '-',
                            number_format($row['total_contribution'], 2),
                            isset($row['gross']) && $row['gross'] !== null ? number_format($row['gross'], 2) : '',
                            $row['philhealth_number'],
                        ] as $index => $cell)
                            <td @class(['whitespace-nowrap px-2 py-1.5 text-gray-800', 'text-right' => $index >= 4 && $index <= 9]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' . ($index >= 4 && $index <= 9 ? 'text-align:right;' : '') : '' }}">{{ $cell }}</td>
                        @endforeach
                    @endif
                </tr>
                @empty
                    <tr>
                        <td colspan="11" @class(['px-3 py-8 text-center text-gray-500' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:16px;text-align:center;color:#555;' : '' }}">
                            No contribution rows found for the selected batches.
                        </td>
                    </tr>
                @endforelse
            @else
                @forelse ($employeeRows as $row)
                    <tr>
                        @foreach ([
                            $row['birthdate'],
                            $row['pagibig_number'],
                            $row['tin_number'],
                            $row['no'],
                            $row['last_name'],
                            $row['first_name'],
                            $row['middle_name'],
                            number_format($row['employee_share'], 2),
                            number_format($row['employer_share'], 2),
                            number_format($row['total_contribution'], 2),
                        ] as $index => $cell)
                            <td @class(['whitespace-nowrap px-2 py-1.5 text-gray-800', 'text-right' => $index >= 7]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' . ($index >= 7 ? 'text-align:right;' : '') : '' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" @class(['px-3 py-8 text-center text-gray-500' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:16px;text-align:center;color:#555;' : '' }}">
                            No contribution rows found for the selected batches.
                        </td>
                    </tr>
                @endforelse
            @endif
        </tbody>
        @if (($isPagibig && $sections !== []) || ($isPhilhealth && $employeeRows !== []))
            <tfoot @class(['bg-slate-50 font-semibold' => ! $forPdf])>
                @foreach (($isPhilhealth ? ['SUB-TOTAL', 'GRAND TOTAL', 'Net'] : ['GRAND TOTAL', 'Net']) as $label)
                    <tr>
                        @if ($isPhilhealth)
                            <td colspan="4" @class(['px-2 py-1.5 text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}">{{ $label }}</td>
                            <td @class(['px-2 py-1.5 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($totals['employee_share'] ?? 0, 2) }}</td>
                            <td @class(['px-2 py-1.5 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($totals['employer_share'] ?? 0, 2) }}</td>
                            <td @class(['px-2 py-1.5 text-center text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}">0</td>
                            <td @class(['px-2 py-1.5 text-center text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}">0</td>
                            <td @class(['px-2 py-1.5 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($totals['total_contribution'] ?? 0, 2) }}</td>
                            <td @class(['px-2 py-1.5 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($totals['gross'] ?? 0, 2) }}</td>
                            <td @class(['px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                        @else
                            <td colspan="7" @class(['px-2 py-1.5 text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}">{{ $label }}</td>
                            <td @class(['px-2 py-1.5 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($totals['employee_share'] ?? 0, 2) }}</td>
                            <td @class(['px-2 py-1.5 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($totals['employer_share'] ?? 0, 2) }}</td>
                            <td @class(['px-2 py-1.5 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($totals['total_contribution'] ?? 0, 2) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tfoot>
        @endif
    </table>
</div>
