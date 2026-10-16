<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $form->name }}</title>
    @php $pdfMode = empty($browserPreview); @endphp
    <style>
        {!! '@' !!}page {
            size: legal portrait;
            margin: {{ $pdfMode ? '36pt 40pt' : '0' }};
        }
        html, body { margin: 0; padding: 0; color: #000; font-family: "DejaVu Serif", "Times New Roman", serif; font-size: 11pt; }
        * { font-family: "DejaVu Serif", "Times New Roman", serif; }
        .memo-page {
            background: #fff;
            @if ($pdfMode)
            width: 520pt;
            @else
            width: 8.5in;
            min-height: 14in;
            margin: 0 auto 16px;
            padding: 0.55in 0.7in;
            box-sizing: border-box;
            box-shadow: 0 1px 8px rgba(15, 23, 42, 0.12);
            @endif
        }
        .sans { font-family: "DejaVu Sans", Arial, sans-serif; }
        table.sheet, table.meta, table.offenses { width: 520pt; border-collapse: collapse; table-layout: fixed; }
        table.meta td { vertical-align: top; padding: 0 2pt 8pt 0; font-size: 11pt; }
        table.meta td.party { white-space: nowrap; }
        table.offenses td { border: 1px solid #000; vertical-align: top; padding: 5pt 4pt; font-size: 11pt; line-height: 1.3; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .italic { font-style: italic; }
        .under { text-decoration: underline; }
    </style>
</head>
<body>
@if (! empty($browserPreview))<div data-memo-preview-desk>@endif
<div class="memo-page">
    <table class="sheet">
        <tr>
            <td style="width:56pt;" valign="middle">
                @if ($memo['logo_src'] !== '')
                    <img src="{{ $memo['logo_src'] }}" alt="" width="52" height="52">
                @endif
            </td>
            <td class="center" valign="middle" style="width:408pt;">
                <div class="sans bold" style="font-size:14pt;white-space:nowrap;">ICCT COLLEGES FOUNDATION, INC.</div>
                <div class="sans bold" style="font-size:11pt;margin-top:2px;white-space:nowrap;">V.V Soliven Avenue II, Cainta, Rizal</div>
            </td>
            <td style="width:56pt;"></td>
        </tr>
    </table>

    <p class="center bold under" style="margin:16px 0 0;letter-spacing:1px;text-align:center;">M E M O R A N D U M</p>
    <p class="right bold under" style="margin:18px 0 0;font-size:11pt;text-align:right;">Control No. {{ $memo['control_no'] }}</p>
    <p class="bold italic under" style="margin:12px 0 0;">Confidential</p>

    <table class="meta" style="margin-top:14px;">
        <tr>
            <td style="width:62pt;">From:</td>
            <td class="party" style="width:200pt;">{{ $memo['from_name'] }}</td>
            <td style="width:14pt;" class="center">{{ $memo['from_title'] !== '' ? '-' : '' }}</td>
            <td class="party" style="width:244pt;">{{ $memo['from_title'] }}</td>
        </tr>
        <tr>
            <td>To:</td>
            <td class="party">{{ $memo['to_name'] }}</td>
            <td class="center">{{ $memo['to_title'] !== '' ? '-' : '' }}</td>
            <td class="party">{{ $memo['to_title'] }}</td>
        </tr>
        <tr>
            <td>Date:</td>
            <td colspan="3">{{ $memo['date'] }}</td>
        </tr>
        <tr>
            <td>Subject:</td>
            <td colspan="3" class="bold">{{ $memo['subject'] }}</td>
        </tr>
        @foreach ($memo['cc'] as $index => $line)
            <tr>
                <td style="padding-bottom:2px;">{!! $index === 0 ? '<span class="bold">C</span>c:' : '' !!}</td>
                <td class="party" style="padding-bottom:2px;">{{ $line['name'] }}</td>
                <td class="center" style="padding-bottom:2px;">{{ $line['title'] !== '' ? '-' : '' }}</td>
                <td class="party" style="padding-bottom:2px;">{{ $line['title'] }}</td>
            </tr>
        @endforeach
    </table>

    <table class="sheet" style="margin-top:6px;">
        <tr><td style="border-bottom:1px solid #000;font-size:1px;line-height:1px;">&nbsp;</td></tr>
    </table>

    <p style="margin:14px 0 0;text-align:justify;line-height:1.35;">We have noted your continued absences and tardiness for the months of {{ $memo['body_period'] }} as shown on the attached summary of attendance.</p>
    <p style="margin:12px 0 0;text-align:justify;line-height:1.35;">Please be reminded that as per the Code of Offenses &amp; Table of Penalties, Table II. Offenses Against Administration:</p>

    <table class="offenses" style="margin-top:10px;">
        <tr>
            <td style="width:88pt;" class="center bold italic">Section 8</td>
            <td style="width:268pt;">
                <span class="under bold">Whole day</span> absence without official leave (AWOL).<br><br>
                (Each day of absence without notice will be on an accrual basis)
            </td>
            <td style="width:164pt;" class="center bold italic">B (Written Warning)</td>
        </tr>
        <tr>
            <td class="center bold italic">Section 23</td>
            <td class="center">Habitual tardiness, committing five (5) times late in a month. For faculty, <span class="italic">please see Appendix A</span></td>
            <td class="center"><span class="bold italic">A (</span><span class="italic">Verbal Reprimand - but recorded for reference)</span></td>
        </tr>
    </table>

    <p style="margin:12px 0 0;text-align:justify;line-height:1.35;">Please submit a written explanation regarding the issue within forty-eight (48) hours upon receipt of this memorandum.</p>
    <p style="margin:12px 0 0;text-align:justify;line-height:1.35;">We look forward to your cooperation.</p>
    <p style="margin:26px 0 0;line-height:1.35;">Aurora G. Esma<br>Vice-President, HR &amp; Administration</p>
</div>
@if (! empty($browserPreview))
</div>
<script>
(function () {
    var desk = document.querySelector('[data-memo-preview-desk]');
    if (!desk) return;
    var fit = function () {
        desk.style.zoom = String(Math.min(1, (desk.clientWidth || 816) / (8.5 * 96)));
    };
    fit();
    window.addEventListener('resize', fit);
})();
</script>
@endif
</body>
</html>
