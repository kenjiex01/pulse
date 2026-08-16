@php
    $meta = $preview['meta'] ?? [];
    $certificates = $meta['certificates'] ?? [];
    $employer = $meta['employer'] ?? [];
    $forPdf = (bool) ($forPdf ?? false);
    $signatory = strtoupper((string) ($meta['signatory_name'] ?? ''));

    $money = static fn ($value): string => number_format((float) $value, 2, '.', ',');

    $tinParts = static function (?string $tin): array {
        $digits = preg_replace('/\D/', '', (string) $tin) ?? '';
        if ($digits === '') {
            return ['', '', '', ''];
        }
        $digits = str_pad(substr($digits, 0, 12), 12, ' ', STR_PAD_RIGHT);

        return [
            trim(substr($digits, 0, 3)),
            trim(substr($digits, 3, 3)),
            trim(substr($digits, 6, 3)),
            trim(substr($digits, 9, 4)),
        ];
    };

    $chars = static function (?string $value, int $length): array {
        $raw = preg_replace('/\D/', '', (string) $value) ?? '';
        $raw = str_pad(substr($raw, 0, $length), $length, ' ', STR_PAD_LEFT);

        return str_split($raw);
    };
@endphp

<style>
    .bir2316-page {
        font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
        color: #000;
        background: #fff;
        border: 1.5px solid #000;
        box-sizing: border-box;
        width: 100%;
        max-width: 210mm;
        margin: 0 auto 18px;
        font-size: {{ $forPdf ? '7.2px' : '10px' }};
        line-height: 1.15;
    }
    .bir2316-page * { box-sizing: border-box; }
    .bir2316-hdr {
        display: table;
        width: 100%;
        border-bottom: 1.5px solid #000;
    }
    .bir2316-hdr > div { display: table-cell; vertical-align: middle; padding: 4px 6px; }
    .bir2316-hdr-left { width: 22%; font-size: 0.85em; }
    .bir2316-hdr-mid { width: 56%; text-align: center; }
    .bir2316-hdr-right { width: 22%; text-align: right; font-size: 0.85em; }
    .bir2316-seal {
        display: inline-block;
        width: {{ $forPdf ? '28px' : '36px' }};
        height: {{ $forPdf ? '28px' : '36px' }};
        border: 1.5px solid #000;
        border-radius: 50%;
        line-height: {{ $forPdf ? '28px' : '36px' }};
        font-weight: 700;
        font-size: 0.85em;
        vertical-align: middle;
        margin-right: 4px;
    }
    .bir2316-title { font-size: 1.35em; font-weight: 700; margin: 2px 0; }
    .bir2316-subtitle { font-size: 0.9em; }
    .bir2316-formno { font-weight: 700; font-size: 1.05em; }
    .bir2316-yearrow {
        display: table;
        width: 100%;
        border-bottom: 1.5px solid #000;
    }
    .bir2316-yearrow > div { display: table-cell; vertical-align: middle; padding: 3px 5px; border-right: 1px solid #000; }
    .bir2316-yearrow > div:last-child { border-right: 0; }
    .bir2316-body {
        display: table;
        width: 100%;
        table-layout: fixed;
    }
    .bir2316-col {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        border-right: 1.5px solid #000;
    }
    .bir2316-col:last-child { border-right: 0; }
    .bir2316-band {
        background: #e8e8e8;
        border-bottom: 1px solid #000;
        font-weight: 700;
        text-align: center;
        padding: 2px 4px;
        font-size: 0.95em;
    }
    .bir2316-band-sub {
        background: #f3f3f3;
        border-bottom: 1px solid #000;
        font-weight: 700;
        padding: 2px 4px;
        font-size: 0.9em;
    }
    .bir2316-row {
        display: table;
        width: 100%;
        border-bottom: 1px solid #000;
        min-height: {{ $forPdf ? '14px' : '18px' }};
    }
    .bir2316-row > .lbl,
    .bir2316-row > .val {
        display: table-cell;
        vertical-align: middle;
        padding: 1px 3px;
    }
    .bir2316-row > .lbl { width: 68%; border-right: 1px solid #000; }
    .bir2316-row > .val { width: 32%; text-align: right; font-weight: 600; }
    .bir2316-field {
        border-bottom: 1px solid #000;
        padding: 2px 4px;
    }
    .bir2316-field-label {
        font-size: 0.82em;
        color: #222;
        display: block;
        margin-bottom: 1px;
    }
    .bir2316-field-value {
        font-weight: 700;
        min-height: 1.1em;
        word-break: break-word;
    }
    .bir2316-inline {
        display: table;
        width: 100%;
    }
    .bir2316-inline > div {
        display: table-cell;
        vertical-align: top;
        border-right: 1px solid #000;
        padding: 2px 4px;
    }
    .bir2316-inline > div:last-child { border-right: 0; }
    .bir2316-digits {
        display: inline-block;
        white-space: nowrap;
        vertical-align: middle;
    }
    .bir2316-digit {
        display: inline-block;
        width: {{ $forPdf ? '9px' : '12px' }};
        height: {{ $forPdf ? '11px' : '14px' }};
        border: 1px solid #000;
        text-align: center;
        font-weight: 700;
        line-height: {{ $forPdf ? '11px' : '14px' }};
        margin-right: -1px;
        font-size: 0.95em;
    }
    .bir2316-tinseg {
        display: inline-block;
        min-width: {{ $forPdf ? '28px' : '36px' }};
        border: 1px solid #000;
        text-align: center;
        font-weight: 700;
        padding: 1px 2px;
        margin-right: 3px;
    }
    .bir2316-check {
        display: inline-block;
        width: {{ $forPdf ? '9px' : '12px' }};
        height: {{ $forPdf ? '9px' : '12px' }};
        border: 1px solid #000;
        text-align: center;
        font-weight: 700;
        line-height: {{ $forPdf ? '9px' : '12px' }};
        margin-right: 3px;
        font-size: 0.85em;
    }
    .bir2316-declare {
        border-top: 1.5px solid #000;
        padding: 4px 6px;
        font-size: 0.82em;
        text-align: justify;
    }
    .bir2316-sigs {
        display: table;
        width: 100%;
        border-top: 1px solid #000;
    }
    .bir2316-sigs > div {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        padding: 6px 8px 4px;
        border-right: 1px solid #000;
    }
    .bir2316-sigs > div:last-child { border-right: 0; }
    .bir2316-sig-line {
        border-bottom: 1px solid #000;
        min-height: {{ $forPdf ? '18px' : '24px' }};
        font-weight: 700;
        text-align: center;
        padding-top: {{ $forPdf ? '8px' : '12px' }};
    }
    .bir2316-sig-caption {
        text-align: center;
        font-size: 0.8em;
        margin-top: 2px;
    }
    .bir2316-subfile {
        display: table;
        width: 100%;
        border-top: 1.5px solid #000;
    }
    .bir2316-subfile > div {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        padding: 4px 6px;
        border-right: 1px solid #000;
        font-size: 0.8em;
    }
    .bir2316-subfile > div:last-child { border-right: 0; }
    .bir2316-note {
        border-top: 1px solid #000;
        padding: 2px 6px;
        font-size: 0.75em;
    }
    .bir2316-amt-hdr {
        display: table;
        width: 100%;
        border-bottom: 1px solid #000;
        background: #f7f7f7;
    }
    .bir2316-amt-hdr > span {
        display: table-cell;
        padding: 1px 3px;
        font-weight: 700;
        font-size: 0.85em;
    }
    .bir2316-amt-hdr > span:last-child { text-align: right; width: 32%; border-left: 1px solid #000; }
</style>

@forelse ($certificates as $c)
    @php
        $empTin = $tinParts($c['tin'] ?? '');
        $erTin = $tinParts($employer['tin'] ?? '');
        $zipEmp = $chars($c['postal_code'] ?? '', 4);
        $zipEr = $chars($employer['zip'] ?? '', 4);
        $dob = $chars($c['birth_date_mmddyyyy'] ?? '', 8);
    @endphp

    <div class="bir2316-page" @if ($forPdf && ! $loop->last) style="page-break-after: always;" @endif>

        {{-- Header --}}
        <div class="bir2316-hdr">
            <div class="bir2316-hdr-left">
                <div>For BIR Use Only</div>
                <div style="margin-top:4px;">BCS/ Item:</div>
                <div class="bir2316-formno" style="margin-top:8px;">BIR Form No. 2316</div>
                <div>September 2021 (ENCS)</div>
            </div>
            <div class="bir2316-hdr-mid">
                <span class="bir2316-seal">BIR</span>
                <div style="display:inline-block; vertical-align:middle; text-align:left;">
                    <div style="font-size:0.85em;">Republic of the Philippines</div>
                    <div style="font-size:0.85em;">Department of Finance</div>
                    <div style="font-weight:700;">Bureau of Internal Revenue</div>
                </div>
                <div class="bir2316-title">Certificate of Compensation Payment/Tax Withheld</div>
                <div class="bir2316-subtitle">For Compensation Payment With or Without Tax Withheld</div>
                <div style="font-size:0.8em;margin-top:2px;">Fill in all applicable spaces. Mark all appropriate boxes with an "X".</div>
            </div>
            <div class="bir2316-hdr-right">
                <div style="border:1px solid #000;padding:6px 4px;text-align:center;font-family:monospace;letter-spacing:1px;">||||| 2316 |||||</div>
                <div style="margin-top:4px;font-weight:700;">2316 09/21 ENCS</div>
            </div>
        </div>

        {{-- Year / Period --}}
        <div class="bir2316-yearrow">
            <div style="width:28%;">
                <span class="bir2316-field-label">1 For the Year (YYYY)</span>
                <span style="font-weight:700;font-size:1.2em;">{{ $c['for_year'] ?? '' }}</span>
            </div>
            <div style="width:72%;">
                <span class="bir2316-field-label">2 For the Period</span>
                From (MM/DD)
                <span class="bir2316-tinseg">{{ $c['period_from_mm'] ?? '' }}</span>
                <span class="bir2316-tinseg">{{ $c['period_from_dd'] ?? '' }}</span>
                &nbsp; To (MM/DD)
                <span class="bir2316-tinseg">{{ $c['period_to_mm'] ?? '' }}</span>
                <span class="bir2316-tinseg">{{ $c['period_to_dd'] ?? '' }}</span>
            </div>
        </div>

        <div class="bir2316-body">
            {{-- LEFT COLUMN --}}
            <div class="bir2316-col">
                <div class="bir2316-band">Part I - Employee Information</div>

                <div class="bir2316-field">
                    <span class="bir2316-field-label">3 TIN</span>
                    <span class="bir2316-tinseg">{{ $empTin[0] }}</span>
                    <span class="bir2316-tinseg">{{ $empTin[1] }}</span>
                    <span class="bir2316-tinseg">{{ $empTin[2] }}</span>
                    <span class="bir2316-tinseg">{{ $empTin[3] }}</span>
                </div>

                <div class="bir2316-inline" style="border-bottom:1px solid #000;">
                    <div style="width:78%;">
                        <span class="bir2316-field-label">4 Employee's Name (Last Name, First Name, Middle Name)</span>
                        <div class="bir2316-field-value">{{ strtoupper((string) ($c['employee_name'] ?? '')) }}</div>
                    </div>
                    <div style="width:22%;">
                        <span class="bir2316-field-label">5 RDO Code</span>
                        <div class="bir2316-field-value">{{ $c['employee_rdo'] ?? '' }}</div>
                    </div>
                </div>

                <div class="bir2316-inline" style="border-bottom:1px solid #000;">
                    <div style="width:78%;">
                        <span class="bir2316-field-label">6 Registered Address</span>
                        <div class="bir2316-field-value">{{ strtoupper((string) ($c['address'] ?? '')) }}</div>
                    </div>
                    <div style="width:22%;">
                        <span class="bir2316-field-label">6A ZIP Code</span>
                        <span class="bir2316-digits">
                            @foreach ($zipEmp as $ch)
                                <span class="bir2316-digit">{{ $ch !== ' ' ? $ch : '' }}</span>
                            @endforeach
                        </span>
                    </div>
                </div>

                <div class="bir2316-inline" style="border-bottom:1px solid #000;">
                    <div style="width:78%;">
                        <span class="bir2316-field-label">6B Local Home Address</span>
                        <div class="bir2316-field-value">{{ strtoupper((string) ($c['local_home_address'] ?? '')) }}&nbsp;</div>
                    </div>
                    <div style="width:22%;">
                        <span class="bir2316-field-label">6C ZIP Code</span>
                        <span class="bir2316-digits">
                            @for ($i = 0; $i < 4; $i++)
                                <span class="bir2316-digit"></span>
                            @endfor
                        </span>
                    </div>
                </div>

                <div class="bir2316-inline" style="border-bottom:1px solid #000;">
                    <div style="width:78%;">
                        <span class="bir2316-field-label">6D Foreign Address</span>
                        <div class="bir2316-field-value">&nbsp;</div>
                    </div>
                    <div style="width:22%;">
                        <span class="bir2316-field-label">6E ZIP Code</span>
                        <span class="bir2316-digits">
                            @for ($i = 0; $i < 4; $i++)
                                <span class="bir2316-digit"></span>
                            @endfor
                        </span>
                    </div>
                </div>

                <div class="bir2316-inline" style="border-bottom:1px solid #000;">
                    <div style="width:55%;">
                        <span class="bir2316-field-label">7 Date of Birth (MM/DD/YYYY)</span>
                        <span class="bir2316-digits">
                            @foreach ($dob as $i => $ch)
                                <span class="bir2316-digit">{{ $ch !== ' ' ? $ch : '' }}</span>
                                @if (in_array($i, [1, 3], true))
                                    <span style="padding:0 1px;">/</span>
                                @endif
                            @endforeach
                        </span>
                    </div>
                    <div style="width:45%;">
                        <span class="bir2316-field-label">8 Contact Number</span>
                        <div class="bir2316-field-value">{{ $c['phone'] ?? '' }}</div>
                    </div>
                </div>

                <div class="bir2316-inline" style="border-bottom:1px solid #000;">
                    <div style="width:50%;">
                        <span class="bir2316-field-label">9 Statutory Minimum Wage rate per day</span>
                        <div class="bir2316-field-value" style="text-align:right;">{{ ($c['is_mwe'] ?? false) ? $money($c['smw_rate_per_day'] ?? 0) : '' }}</div>
                    </div>
                    <div style="width:50%;">
                        <span class="bir2316-field-label">10 Statutory Minimum Wage rate per month</span>
                        <div class="bir2316-field-value" style="text-align:right;">{{ ($c['is_mwe'] ?? false) ? $money($c['smw_rate_per_month'] ?? 0) : '' }}</div>
                    </div>
                </div>

                <div class="bir2316-field">
                    <span class="bir2316-check">{{ ($c['is_mwe'] ?? false) ? 'X' : '' }}</span>
                    <span style="font-size:0.85em;">11 Minimum Wage Earner whose compensation is exempt from withholding tax and not subject to income tax</span>
                </div>

                <div class="bir2316-band">Part II - Employer Information (Present)</div>

                <div class="bir2316-field">
                    <span class="bir2316-field-label">12 TIN</span>
                    <span class="bir2316-tinseg">{{ $erTin[0] }}</span>
                    <span class="bir2316-tinseg">{{ $erTin[1] }}</span>
                    <span class="bir2316-tinseg">{{ $erTin[2] }}</span>
                    <span class="bir2316-tinseg">{{ $erTin[3] }}</span>
                </div>

                <div class="bir2316-field">
                    <span class="bir2316-field-label">13 Employer's Name</span>
                    <div class="bir2316-field-value">{{ strtoupper((string) ($employer['name'] ?? '')) }}</div>
                </div>

                <div class="bir2316-inline" style="border-bottom:1px solid #000;">
                    <div style="width:78%;">
                        <span class="bir2316-field-label">14 Registered Address</span>
                        <div class="bir2316-field-value">{{ strtoupper((string) ($employer['address'] ?? '')) }}</div>
                    </div>
                    <div style="width:22%;">
                        <span class="bir2316-field-label">14A ZIP Code</span>
                        <span class="bir2316-digits">
                            @foreach ($zipEr as $ch)
                                <span class="bir2316-digit">{{ $ch !== ' ' ? $ch : '' }}</span>
                            @endforeach
                        </span>
                    </div>
                </div>

                <div class="bir2316-field">
                    <span class="bir2316-field-label">15 Type of Employer</span>
                    <span class="bir2316-check">X</span> Main Employer
                    &nbsp;&nbsp;
                    <span class="bir2316-check"></span> Secondary Employer
                </div>

                <div class="bir2316-band">Part III - Employer Information (Previous)</div>

                <div class="bir2316-field">
                    <span class="bir2316-field-label">16 TIN</span>
                    <span class="bir2316-tinseg">&nbsp;&nbsp;&nbsp;</span>
                    <span class="bir2316-tinseg">&nbsp;&nbsp;&nbsp;</span>
                    <span class="bir2316-tinseg">&nbsp;&nbsp;&nbsp;</span>
                    <span class="bir2316-tinseg">&nbsp;&nbsp;&nbsp;&nbsp;</span>
                </div>
                <div class="bir2316-field">
                    <span class="bir2316-field-label">17 Employer's Name</span>
                    <div class="bir2316-field-value">&nbsp;</div>
                </div>
                <div class="bir2316-inline" style="border-bottom:1px solid #000;">
                    <div style="width:78%;">
                        <span class="bir2316-field-label">18 Registered Address</span>
                        <div class="bir2316-field-value">&nbsp;</div>
                    </div>
                    <div style="width:22%;">
                        <span class="bir2316-field-label">18A ZIP Code</span>
                        <span class="bir2316-digits">
                            @for ($i = 0; $i < 4; $i++)
                                <span class="bir2316-digit"></span>
                            @endfor
                        </span>
                    </div>
                </div>

                <div class="bir2316-band">Part IVA - Summary</div>

                @foreach ([
                    ['19', 'Gross Compensation Income from Present Employer (Sum of Items 38 and 52)', $c['item_19'] ?? 0],
                    ['20', 'Less: Total Non-Taxable/Exempt Compensation Income from Present Employer (From Item 38)', $c['item_20'] ?? 0],
                    ['21', 'Taxable Compensation Income from Present Employer (Item 19 Less Item 20) (From Item 52)', $c['item_21'] ?? 0],
                    ['22', 'Add: Taxable Compensation Income from Previous Employer, if applicable', $c['item_22'] ?? 0],
                    ['23', 'Gross Taxable Compensation Income (Sum of Items 21 and 22)', $c['item_23'] ?? 0],
                    ['24', 'Tax Due', $c['item_24'] ?? 0],
                ] as [$no, $label, $amt])
                    <div class="bir2316-row">
                        <div class="lbl"><strong>{{ $no }}</strong> {{ $label }}</div>
                        <div class="val">{{ $money($amt) }}</div>
                    </div>
                @endforeach

                <div class="bir2316-band-sub">25 Amount of Taxes Withheld</div>
                <div class="bir2316-row">
                    <div class="lbl">25A Present Employer</div>
                    <div class="val">{{ $money($c['item_25a'] ?? 0) }}</div>
                </div>
                <div class="bir2316-row">
                    <div class="lbl">25B Previous Employer, if applicable</div>
                    <div class="val">{{ $money($c['item_25b'] ?? 0) }}</div>
                </div>
                <div class="bir2316-row">
                    <div class="lbl"><strong>26</strong> Total Amount of Taxes Withheld as adjusted (Sum of Items 25A and 25B)</div>
                    <div class="val">{{ $money($c['item_26'] ?? 0) }}</div>
                </div>
                <div class="bir2316-row">
                    <div class="lbl"><strong>27</strong> 5% Tax Credit (PERA Act of 2008)</div>
                    <div class="val">{{ $money($c['item_27'] ?? 0) }}</div>
                </div>
                <div class="bir2316-row" style="border-bottom:0;">
                    <div class="lbl"><strong>28</strong> Total Taxes Withheld (Sum of Items 26 and 27)</div>
                    <div class="val">{{ $money($c['item_28'] ?? 0) }}</div>
                </div>
            </div>

            {{-- RIGHT COLUMN --}}
            <div class="bir2316-col">
                <div class="bir2316-band">Part IV-B Details of Compensation Income and Tax Withheld from Present Employer</div>
                <div class="bir2316-amt-hdr">
                    <span>A. NON-TAXABLE/EXEMPT COMPENSATION INCOME</span>
                    <span>Amount</span>
                </div>

                @foreach ([
                    ['29', 'Basic Salary (including the exempt P250,000 & below or the Statutory Minimum Wage of the MWE)', $c['item_29'] ?? 0],
                    ['30', 'Holiday Pay (MWE)', $c['item_30'] ?? 0],
                    ['31', 'Overtime Pay (MWE)', $c['item_31'] ?? 0],
                    ['32', 'Night Shift Differential (MWE)', $c['item_32'] ?? 0],
                    ['33', 'Hazard Pay (MWE)', $c['item_33'] ?? 0],
                    ['34', '13th Month Pay and Other Benefits (maximum of P90,000)', $c['item_34'] ?? 0],
                    ['35', 'De Minimis Benefits', $c['item_35'] ?? 0],
                    ['36', 'SSS, GSIS, PHIC & PAG-IBIG Contributions and Union Dues (Employee share only)', $c['item_36'] ?? 0],
                    ['37', 'Salaries and Other Forms of Compensation', $c['item_37'] ?? 0],
                    ['38', 'Total Non-Taxable/Exempt Compensation Income (Sum of Items 29 to 37)', $c['item_38'] ?? 0],
                ] as [$no, $label, $amt])
                    <div class="bir2316-row">
                        <div class="lbl"><strong>{{ $no }}</strong> {{ $label }}</div>
                        <div class="val">{{ $money($amt) }}</div>
                    </div>
                @endforeach

                <div class="bir2316-band-sub">B. TAXABLE COMPENSATION INCOME &nbsp;&nbsp; REGULAR</div>

                @foreach ([
                    ['39', 'Basic Salary', $c['item_39'] ?? 0],
                    ['40', 'Representation', $c['item_40'] ?? 0],
                    ['41', 'Transportation', $c['item_41'] ?? 0],
                    ['42', 'Cost of Living Allowance (COLA)', $c['item_42'] ?? 0],
                    ['43', 'Fixed Housing Allowance', $c['item_43'] ?? 0],
                ] as [$no, $label, $amt])
                    <div class="bir2316-row">
                        <div class="lbl"><strong>{{ $no }}</strong> {{ $label }}</div>
                        <div class="val">{{ $amt > 0 || in_array($no, ['39'], true) ? $money($amt) : '' }}</div>
                    </div>
                @endforeach

                <div class="bir2316-row">
                    <div class="lbl">
                        <strong>44</strong> Others (Specify)
                        <div style="margin-top:1px;">44A {{ $c['item_44a_label'] ?? '' }}</div>
                    </div>
                    <div class="val">{{ (($c['item_44a'] ?? 0) > 0) ? $money($c['item_44a']) : '' }}</div>
                </div>
                <div class="bir2316-row">
                    <div class="lbl">44B {{ $c['item_44b_label'] ?? '' }}</div>
                    <div class="val">{{ (($c['item_44b'] ?? 0) > 0) ? $money($c['item_44b']) : '' }}</div>
                </div>

                <div class="bir2316-band-sub">SUPPLEMENTARY</div>

                @foreach ([
                    ['45', 'Commission', $c['item_45'] ?? 0],
                    ['46', 'Profit Sharing', $c['item_46'] ?? 0],
                    ['47', "Fees Including Director's Fees", $c['item_47'] ?? 0],
                    ['48', 'Taxable 13th Month Pay Benefits', $c['item_48'] ?? 0],
                    ['49', 'Hazard Pay', $c['item_49'] ?? 0],
                    ['50', 'Overtime Pay', $c['item_50'] ?? 0],
                ] as [$no, $label, $amt])
                    <div class="bir2316-row">
                        <div class="lbl"><strong>{{ $no }}</strong> {{ $label }}</div>
                        <div class="val">{{ $amt > 0 || in_array($no, ['48', '50'], true) ? $money($amt) : '' }}</div>
                    </div>
                @endforeach

                <div class="bir2316-row">
                    <div class="lbl"><strong>51</strong> Others (Specify)<br>51A {{ $c['item_51a_label'] ?? '' }}</div>
                    <div class="val">{{ (($c['item_51a'] ?? 0) > 0) ? $money($c['item_51a']) : '' }}</div>
                </div>
                <div class="bir2316-row">
                    <div class="lbl">51B {{ $c['item_51b_label'] ?? '' }}</div>
                    <div class="val">{{ (($c['item_51b'] ?? 0) > 0) ? $money($c['item_51b']) : '' }}</div>
                </div>
                <div class="bir2316-row" style="border-bottom:0;">
                    <div class="lbl"><strong>52</strong> Total Taxable Compensation Income (Sum of Items 39 to 51B)</div>
                    <div class="val">{{ $money($c['item_52'] ?? 0) }}</div>
                </div>
            </div>
        </div>

        <div class="bir2316-declare">
            I/We declare, under the penalties of perjury, that this certificate has been made in good faith, verified by me/us, and to the best of my/our knowledge and belief, is true and correct, pursuant to the provisions of the National Internal Revenue Code, as amended, and the regulations issued under authority thereof. Further, I/We give my/our consent to the processing of my/our information as contemplated under the Data Privacy Act of 2012 (R.A. No. 10173) for legitimate and lawful purposes.
        </div>

        <div class="bir2316-sigs">
            <div>
                <div class="bir2316-sig-line">{{ $signatory }}</div>
                <div class="bir2316-sig-caption">Present Employer/Authorized Agent Signature Over Printed Name</div>
            </div>
            <div>
                <div class="bir2316-sig-line">{{ strtoupper((string) ($c['employee_name_upper'] ?? $c['employee_name'] ?? '')) }}</div>
                <div class="bir2316-sig-caption">Employee Signature Over Printed Name</div>
            </div>
        </div>

        <div class="bir2316-band">To be accomplished under substituted filing</div>
        <div class="bir2316-subfile">
            <div>
                <strong>53</strong> I declare that I have filed the corresponding BIR Form No. 1604-C (Annual Information Return of Income Taxes Withheld on Compensation).
                <div class="bir2316-sig-line" style="margin-top:10px;">{{ $signatory }}</div>
                <div class="bir2316-sig-caption">Present Employer/Authorized Agent Signature Over Printed Name</div>
            </div>
            <div>
                <strong>54</strong> I declare that I qualify for substituted filing because I received purely compensation income from only one employer in the Philippines for the calendar year; the income tax withheld is equal to income tax due; and I have no other income subject to tax.
                <div class="bir2316-sig-line" style="margin-top:10px;">{{ strtoupper((string) ($c['employee_name_upper'] ?? $c['employee_name'] ?? '')) }}</div>
                <div class="bir2316-sig-caption">Employee Signature Over Printed Name</div>
            </div>
        </div>

        <div class="bir2316-note">
            *NOTE: The BIR Data Privacy is in the BIR website (www.bir.gov.ph).
            @if (! empty($c['batch_label']))
                &nbsp; Source batch: {{ $c['batch_label'] }}
            @endif
        </div>
    </div>
@empty
    <p style="font-size:12px;color:#555;">No employees selected or no compensation found in the posted batch.</p>
@endforelse
