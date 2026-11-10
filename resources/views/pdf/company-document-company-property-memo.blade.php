@php
    $fields = $fields ?? [];
    $fontFiles = is_array($fontFiles ?? null) ? $fontFiles : [];
    $browserPreview = (bool) ($browserPreview ?? false);
    $textLeading = $browserPreview ? '13.8pt' : '11pt';
    $subject = $fields['subject'] ?? 'Release of Company Property';
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
        @include('pdf.partials.icct-standard-memo-styles')
        .body { margin: 0 0 13.8pt; text-align: justify; line-height: {{ $textLeading }}; }
        .offense { width: 443.4pt; margin: 0 0 27.8pt -5.7pt; table-layout: fixed; }
        .offense td {
            border: 0.75pt solid #000;
            padding: 2pt 5pt;
            vertical-align: top;
            font-size: 11.5pt;
            line-height: {{ $textLeading }};
        }
        .offense .sec { width: 19.6%; text-align: center; font-weight: 700; }
        .offense .mid { width: 53.8%; }
        .offense .act { width: 26.6%; text-align: center; }
        .sign { margin: 41.6pt 0 0; line-height: {{ $textLeading }}; }
    </style>
</head>
<body>
<div class="sheet">
    @include('pdf.partials.icct-standard-memo-head')
    <p class="body">It has been noted that company property was released or taken out without any accompanying transmittal slip.</p>
    <p class="body">Please be reminded that as per the Code of Offenses &amp; Table of Penalties, Table II. Offenses Against Company Interest and Policy:</p>
    <table class="offense">
        <tr>
            <td class="sec">Section 19</td>
            <td class="mid">Releasing or taking out from any place, warehouse or storage or delivering more than what is authorized in the invoice, delivery receipt, gate pass or authority. This includes non-submission of relevant documents.</td>
            <td class="act">C-D<br>(5 Working Days Suspension or Dismissal)</td>
        </tr>
    </table>
    <p class="body">Please submit a written explanation regarding the issue within forty-eight (48) hours upon receipt of this memorandum.</p>
    <p class="body">We look forward to your cooperation.</p>
    <p class="sign">{{ $fields['from_name'] ?? '' }}<br>{{ $fields['from_title'] ?? '' }}</p>
</div>
</body>
</html>
