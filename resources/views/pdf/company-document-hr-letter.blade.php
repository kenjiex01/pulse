@php
    $fields = $fields ?? [];
    $fontFiles = is_array($fontFiles ?? null) ? $fontFiles : [];
    $browserPreview = (bool) ($browserPreview ?? false);
    $textLeading = $browserPreview ? '13.8pt' : '11pt';
    $variant = (string) ($fields['variant'] ?? '');
    $tall = (bool) ($fields['tall'] ?? false);
    $deliveryFirst = (bool) ($fields['delivery_first'] ?? false);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: {{ $tall ? '612pt 936pt' : 'letter portrait' }}; margin: 0; }
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
        .sheet { padding: 70pt 90pt 28pt 90pt; }
        .letterhead { width: 100%; margin: 0 0 8pt; table-layout: fixed; }
        .letterhead td { padding: 0; vertical-align: middle; }
        .logo-cell { width: 18%; line-height: 0; font-size: 0; }
        .logo-cell img { width: 68pt; height: 66pt; display: block; }
        .name-cell { width: 82%; padding: 0 4pt 0 8pt; text-align: center; }
        .school { margin: 0; font-family: Arial, Helvetica, sans-serif; font-weight: 700; font-size: 15pt; line-height: 18pt; text-align: center; white-space: nowrap; }
        .address {
            margin: 3pt 0 0;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 700;
            font-size: 11pt;
            line-height: 13pt;
            text-align: center;
            white-space: nowrap;
        }
        .topline { width: 432pt; margin: 24pt 0 0; }
        .topline td { vertical-align: top; padding: 0; }
        .via { font-weight: 700; font-size: 10pt; line-height: 12pt; text-decoration: underline; }
        .control { text-align: right; font-weight: 700; font-size: 9pt; line-height: 11pt; text-decoration: underline; }
        .date { margin: 22pt 0 0; font-style: italic; }
        .who { margin: 40pt 0 0; }
        .who .addr { font-style: italic; }
        .dear { margin: 36pt 0 13.8pt; }
        .body { margin: 0 0 13.8pt; text-align: justify; line-height: {{ $textLeading }}; }
        .offense { width: 410pt; margin: 8pt 0 16pt 27pt; table-layout: fixed; }
        .offense td { border: 0.6pt solid #000; padding: 3pt 6pt; vertical-align: middle; font-size: 10pt; line-height: 12pt; }
        .offense .sec { width: 14%; text-align: center; font-weight: 700; }
        .offense .act { width: 22%; text-align: center; font-weight: 700; }
        .close { margin: 16pt 0 0; }
        .sign { margin: 36pt 0 0; line-height: {{ $textLeading }}; }
        .cc { margin: 22pt 0 0; font-size: 9pt; line-height: 12pt; }
        .cc .head { font-style: italic; }
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

    <table class="topline">
        <tr>
            <td class="via" @if (! $deliveryFirst) style="padding-top:10pt;" @endif>{{ $fields['delivery'] ?? '' }}</td>
            <td class="control" @if ($deliveryFirst) style="padding-top:11pt;" @endif>Control No. {{ $fields['control_no'] ?? '' }}</td>
        </tr>
    </table>

    <div class="date">{{ $fields['memo_date'] ?? 'Date' }}</div>
    <div class="who">
        Mr. / Ms. {{ $fields['to_name'] ?? '' }}<br>
        <span class="addr">{{ $fields['address'] ?? 'Residential Address' }}</span>
    </div>
    <p class="dear">Dear Mr. / Ms. {{ $fields['to_name'] ?? '' }},</p>

    @if ($variant === 'hr_notice_to_explain')
        <p class="body">We note of your continued absences since __________.</p>
        <p class="body">Please be advised that this is a failure to provide the 30-days advance notice, as per <b>Section 5.3 of the Employment Contract.</b> Your unprofessional acts and omissions not only prejudiced the students in the subjects you are handling but also caused unquantifiable damage to ICCT Colleges, as well as administrative difficulties.</p>
        <p class="body">There was also no justification or proof of any mitigating circumstance that would explain your absences.</p>
        <p class="body">Please be reminded that your continued absences is a violation under the Code of Offenses &amp; Table of Penalties, Table II. Offenses Against Administration:</p>
        <table class="offense">
            <tr>
                <td class="sec">Section<br>9</td>
                <td>Absence without official leave (AWOL) for five (5) consecutive days or more.</td>
                <td class="act">D (Dismissal)</td>
            </tr>
        </table>
        <p class="body">Therefore, please submit a written explanation within five (5) days from receipt hereof why no disciplinary action should be meted on you including dismissal if warranted for violating the above-stated violation. Failure to submit your explanation within the said period shall be construed as a waiver on your part to provide the same. Thereafter, the charges against you shall be decided based on the records and available evidences at hand.</p>
        <p class="close">Yours sincerely,</p>
    @elseif ($variant === 'hr_letter_nte_followup_2')
        <p class="body">We note your failure to respond to our letter dated __________ (Control No. {{ $fields['control_no'] ?? '' }}).</p>
        <p class="body">This is our 2nd Request for you to submit a written explanation. Therefore, we will expect your clarification until __________ (1-week later) before we proceed with any action.</p>
        <p class="close">Yours sincerely,</p>
    @elseif ($variant === 'hr_letter_nte_followup_3')
        <p class="body">We note your continued failure to respond to our letters dated __________ (Control No. {{ $fields['control_no'] ?? '' }}).</p>
        <p class="body">This is our 3rd Request for you to submit a written explanation. Therefore, we will expect your clarification until __________ (1-week later) before we proceed with an Administrative Investigation.</p>
        <p class="close">Yours sincerely,</p>
    @elseif ($variant === 'hr_letter_admin_investigation')
        <p class="body">Further to your failure to respond to our 3rd Request letter dated __________ (Control No. {{ $fields['control_no'] ?? '' }}), please note that we will now proceed to an Administrative Investigation. The meeting is scheduled on __________, 10 am at Cainta main campus.</p>
        <p class="body">You may bring along a representative or counsel of your choice in the said investigation. Failure to appear on the said investigation shall be considered as waiver on your part to air your side, and the case shall be decided based on the available records.</p>
        <p class="close">For your compliance.</p>
    @else
        <p class="body">Further to your failure to attend the Administrative Hearing scheduled on __________ in connection with the cited offense, as well as the absence of any new/additional material from you, we note the following:</p>
        <p class="body">1. __________<br>2. __________<br>3. __________</p>
        <p class="body">Therefore, ICCT Colleges has found you in violation of the Code of Offenses &amp; Table of Penalties, Table ___. Offenses Against ______, Section ____ (a copy of which you acknowledged receipt, as per Undertaking dated __________:</p>
        <p class="body">Cite provision</p>
        <p class="body">This offense carries a penalty of __________.</p>
        <p class="body">In light of the above, ICCT Colleges has found you liable and deems it right to dismiss your employment.</p>
    @endif

    <p class="sign">{{ $fields['sign_name'] ?? '' }}<br>{{ $fields['sign_title'] ?? '' }}</p>

    @if (($fields['cc_style'] ?? '') === 'short')
        <p class="cc">cc:<br>Department Head</p>
    @else
        <p class="cc">Cc:<br><span class="head">Department Head</span><br>Julie L. Ang &nbsp;&nbsp; - &nbsp;&nbsp; Vice-President, Accounting<br>201 File</p>
    @endif
</div>
</body>
</html>
