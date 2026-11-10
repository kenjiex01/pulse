@php
    $fields = $fields ?? [];
    $ccLines = is_array($fields['cc_lines'] ?? null) ? $fields['cc_lines'] : [];
    $fontFiles = is_array($fontFiles ?? null) ? $fontFiles : [];
    $browserPreview = (bool) ($browserPreview ?? false);
    // Dompdf multiplies line-height by the font's own height, so 11pt there
    // lands on the same 13.8pt leading as the Word file. Browsers use the length as-is.
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
        @if ($fontFiles !== [])
        @font-face { font-family: "Times New Roman"; font-weight: normal; font-style: normal; src: url("{{ $fontFiles['times'] }}") format("truetype"); }
        @font-face { font-family: "Times New Roman"; font-weight: bold; font-style: normal; src: url("{{ $fontFiles['times-bold'] }}") format("truetype"); }
        @font-face { font-family: "Times New Roman"; font-weight: normal; font-style: italic; src: url("{{ $fontFiles['times-italic'] }}") format("truetype"); }
        @font-face { font-family: "Times New Roman"; font-weight: bold; font-style: italic; src: url("{{ $fontFiles['times-bold-italic'] }}") format("truetype"); }
        @font-face { font-family: Arial; font-weight: normal; font-style: normal; src: url("{{ $fontFiles['arial'] }}") format("truetype"); }
        @font-face { font-family: Arial; font-weight: bold; font-style: normal; src: url("{{ $fontFiles['arial-bold'] }}") format("truetype"); }
        @font-face { font-family: "Arial Black"; font-weight: 900; font-style: normal; src: url("{{ $fontFiles['arial-black'] }}") format("truetype"); }
        @endif
        body { font-family: "Times New Roman", Times, serif; font-size: 12pt; line-height: 13.8pt; }
        p { margin: 0; }
        table { border-collapse: collapse; }
        .sheet { padding: 27pt 90pt 24pt 90pt; }
        .letterhead { width: 100%; margin: 0 0 8pt; table-layout: fixed; }
        .letterhead td { padding: 0; vertical-align: middle; }
        .logo-cell { width: 18%; line-height: 0; font-size: 0; }
        .logo-cell img { width: 68pt; height: 66pt; display: block; }
        .name-cell { width: 82%; padding: 0 4pt 0 8pt; text-align: center; }
        .school {
            margin: 0;
            font-family: "Arial Black", Arial, sans-serif;
            font-weight: 900;
            font-size: 14pt;
            line-height: 16pt;
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
            font-size: 12pt;
            line-height: 14pt;
            text-decoration: underline;
        }
        .flagline { width: 100%; margin: 12pt 0 0; }
        .flagline td { padding: 0; vertical-align: baseline; }
        .control {
            margin: 0;
            text-align: right;
            font-weight: 700;
            font-size: 9pt;
            line-height: 11pt;
            text-decoration: underline;
        }
        .confidential {
            margin: 0;
            font-weight: 700;
            font-style: italic;
            font-size: 10pt;
            line-height: 12pt;
            text-decoration: underline;
        }
        .meta { width: 432pt; margin: 21.7pt 0 0; }
        .meta td { padding: 0 0 9.8pt; vertical-align: baseline; font-size: 12pt; line-height: 13.8pt; }
        .meta .label { width: 72pt; }
        .meta .name { width: 144pt; }
        .meta .dash { width: 36pt; }
        .meta .title { font-size: 11pt; }
        .meta tr.cc td { padding: 0; font-size: 9pt; line-height: 11pt; }
        .meta tr.cc td.label { font-size: 11pt; font-weight: 700; line-height: 13pt; }
        .rule { border: 0; border-top: 1pt solid #000; margin: 21pt 0 14.6pt; width: 432pt; }
        .body { margin: 0 0 13.8pt; text-align: justify; line-height: {{ $textLeading }}; }
        .offense { width: 443.4pt; margin: 0 0 27.8pt -5.7pt; table-layout: fixed; }
        .offense td {
            border: 0.75pt solid #000;
            padding: 0.4pt 4pt 2pt 5.5pt;
            vertical-align: top;
            font-size: 12pt;
            line-height: {{ $textLeading }};
        }
        .offense .sec { width: 19.6%; text-align: center; font-weight: 700; font-style: italic; }
        .offense .mid { width: 53.8%; }
        .offense .act { width: 26.6%; text-align: center; font-weight: 700; font-style: italic; padding-left: 2pt; padding-right: 2pt; }
        .sign { margin: 41.6pt 0 0; line-height: {{ $textLeading }}; }
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
            <td class="control">Control No. {{ $fields['control_no'] ?? '' }}</td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="label">From:</td>
            <td class="name">{{ $fields['from_name'] ?? '' }}</td>
            <td class="dash">-</td>
            <td class="title">{{ $fields['from_title'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">To:</td>
            <td class="name">{{ $fields['to_name'] ?? '' }}</td>
            <td class="dash">-</td>
            <td class="title">{{ $fields['to_title'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Date:</td>
            <td colspan="3">{{ $fields['memo_date'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Subject:</td>
            <td colspan="3"><b>{{ $fields['period'] ?? '' }} Attendances</b></td>
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

    <p class="body">We have noted your continued absences and tardiness for the months of {{ $fields['period'] ?? '' }} as shown on the attached summary of attendance.</p>
    <p class="body">Please be reminded that as per the Code of Offenses &amp; Table of Penalties, Table II. Offenses Against Administration:</p>

    <table class="offense">
        <tr>
            <td class="sec">Section 8</td>
            <td class="mid">
                <b><u>Whole day</u> absence without official leave</b><br>
                (AWOL).<br><br>
                (Each day of absence without notice will be on an accrual basis)
            </td>
            <td class="act"><b><i>B (Written Warning)</i></b></td>
        </tr>
        <tr>
            <td class="sec">Section 23</td>
            <td class="mid">Habitual tardiness, committing five (5) times late in a month.&nbsp;&nbsp;For faculty, please see <i>Appendix A</i></td>
            <td class="act"><b><i>A (Verbal Reprimand - but recorded for reference)</i></b></td>
        </tr>
    </table>

    <p class="body">Please submit a written explanation regarding the issue within forty-eight (48) hours upon receipt of this memorandum.</p>
    <p class="body">We look forward to your cooperation.</p>
    <p class="sign">
        {{ $fields['from_name'] ?? '' }}<br>
        {{ $fields['from_title'] ?? '' }}
    </p>
</div>
</body>
</html>
