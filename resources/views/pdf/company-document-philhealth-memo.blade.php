@php
    $fields = $fields ?? [];
    $ccLines = is_array($fields['cc_lines'] ?? null) ? $fields['cc_lines'] : [];
    $fontFiles = is_array($fontFiles ?? null) ? $fontFiles : [];
    $browserPreview = (bool) ($browserPreview ?? false);
    $textLeading = $browserPreview ? '13.8pt' : '11pt';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: letter portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; color: #000; }
        @if ($browserPreview)
        html { background: #e5e7eb; }
        body { width: 612pt; margin: 16pt auto; background: #fff; }
        @else
        body { width: 612pt; }
        @endif
        @include('pdf.partials.icct-memo-font-faces')
        body { font-family: "Times New Roman", Times, serif; font-size: 12pt; line-height: 13.8pt; }
        p { margin: 0; }
        table { border-collapse: collapse; }
        .sheet { padding: 72pt 72pt 28pt 72pt; }
        .letterhead { width: 100%; margin: 0 0 8pt; table-layout: fixed; }
        .letterhead td { padding: 0; vertical-align: middle; }
        .logo-cell { width: 18%; line-height: 0; font-size: 0; }
        .logo-cell img { width: 68pt; height: 66pt; display: block; }
        .name-cell { width: 82%; padding: 0 4pt 0 8pt; text-align: center; }
        .school {
            font-family: "Arial Black", Arial, sans-serif;
            font-weight: 900;
            font-size: 14pt;
            line-height: 16pt;
            margin: 0;
            text-align: center;
            white-space: nowrap;
        }
        .address {
            margin: 3pt 0 0;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 700;
            font-size: 11pt;
            line-height: 13pt;
            text-align: center;
            white-space: nowrap;
        }
        .memorandum {
            margin: 12pt 0 0;
            text-align: center;
            font-weight: 700;
            font-size: 14pt;
            line-height: 16pt;
            text-decoration: underline;
        }
        .flagline { width: 100%; margin: 2pt 0 0; }
        .flagline td { padding: 0; vertical-align: baseline; }
        .confidential {
            margin: 0;
            font-weight: 700;
            font-size: 11pt;
            line-height: 14pt;
        }
        .control {
            margin: 0;
            text-align: right;
            font-weight: 700;
            font-size: 11pt;
            line-height: 14pt;
            text-decoration: underline;
        }
        .meta { width: 100%; margin: 14pt 0 0; }
        .meta td { padding: 0 0 12pt; vertical-align: baseline; font-size: 12pt; line-height: 14pt; }
        .meta .label { width: 72pt; }
        .meta .name { width: 144pt; }
        .meta .dash { width: 36pt; }
        .meta tr.cc td { padding: 0; font-size: 10pt; line-height: 12pt; }
        .rule { border: 0; border-top: 1pt solid #000; margin: 16pt 0 14pt; }
        .body { margin: 0 0 13.8pt; text-align: justify; line-height: {{ $textLeading }}; }
        .sign { margin: 28pt 0 0; line-height: {{ $textLeading }}; }
        .ack { margin: 16pt 0 0; }
        .sigline { margin: 36pt 0 0; width: 100%; }
        .sigline td { font-size: 12pt; line-height: 14pt; vertical-align: top; }
    </style>
</head>
<body>
<div class="sheet">
    <table class="letterhead">
        <tr>
            <td class="logo-cell">
                @if (($fields['logo'] ?? '') !== '')
                    <img src="{{ $fields['logo'] }}" alt="" width="68" height="66">
                @endif
            </td>
            <td class="name-cell">
                <div class="school">ICCT COLLEGES FOUNDATION, INC.</div>
                <div class="address">V.V. Soliven Avenue II, Cainta, Rizal</div>
            </td>
        </tr>
    </table>
    <div class="memorandum">M E M O R A N D U M</div>
    <table class="flagline">
        <tr>
            <td class="confidential">Confidential</td>
            <td class="control">Control No: {{ $fields['control_no'] ?? '' }}</td>
        </tr>
    </table>
    <table class="meta">
        <tr>
            <td class="label">From:</td>
            <td class="name">{{ $fields['from_name'] ?? '' }}</td>
            <td class="dash">-</td>
            <td>{{ $fields['from_title'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">To:</td>
            <td class="name">{{ $fields['to_name'] ?? '' }}</td>
            <td class="dash">-</td>
            <td>{{ $fields['to_title'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Date:</td>
            <td colspan="3">{{ $fields['memo_date'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Subject:</td>
            <td colspan="3">Philhealth Number</td>
        </tr>
        @if ($ccLines !== [])
            <tr class="cc">
                <td class="label">Cc:</td>
                <td class="name">
                    @foreach ($ccLines as $index => $line)
                        @if ($index > 0)<br>@endif{{ $line[0] ?? '' }}
                    @endforeach
                </td>
                <td class="dash">
                    @foreach ($ccLines as $index => $line)
                        @if ($index > 0)<br>@endif-
                    @endforeach
                </td>
                <td>
                    @foreach ($ccLines as $index => $line)
                        @if ($index > 0)<br>@endif{{ $line[1] ?? '' }}
                    @endforeach
                </td>
            </tr>
        @endif
    </table>
    <hr class="rule">
    <p class="body">It has been noted that you started your employment with us on {{ $fields['hire_date'] ?? '' }}. As part of our pre-employment requirements, you were advised to submit your complete and correct PhilHealth Number. This is necessary as we are currently preparing our monthly government remittance.</p>
    <p class="body">Due to the pending submission, the remittance of contributions for all ICCT employees is currently on hold. In this regard, please be advised that {{ $fields['memo_date'] ?? '' }} is the deadline for your compliance.</p>
    <p class="body">Failure to do so will compel us to hold your salary, until such time when you have complied.</p>
    <p class="sign">{{ $fields['from_name'] ?? '' }}<br>{{ $fields['from_title'] ?? '' }}</p>
    <hr class="rule">
    <p class="ack">I confirm understanding and agreement to the above statement.</p>
    <table class="sigline">
        <tr>
            <td style="width:55%;">_________________________<br>Signature above Printed Name</td>
            <td>______________<br>Date</td>
        </tr>
    </table>
</div>
</body>
</html>
