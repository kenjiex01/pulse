@php
    $fields = $fields ?? [];
    $fontFiles = is_array($fontFiles ?? null) ? $fontFiles : [];
    $browserPreview = (bool) ($browserPreview ?? false);
    $textLeading = $browserPreview ? '13.8pt' : '11pt';
    $subject = $fields['subject'] ?? 'Attendances';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: 612pt 936pt; margin: 0; }
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
        .body ol { margin: 0 0 13.8pt 18pt; padding: 0; }
        .body li { margin: 0 0 8pt; }
        .sign { margin: 41.6pt 0 0; line-height: {{ $textLeading }}; }
    </style>
</head>
<body>
<div class="sheet">
    @include('pdf.partials.icct-standard-memo-head')
    <p class="body">Based on official records, you demonstrated a pattern of repeated tardiness and absences. This behavior is unacceptable and has resulted in operational disruptions, scheduling challenges, and increased workload for other staff members.</p>
    <p class="body">Effective immediately, you are expected to correct your attendance and fully comply with your assigned work schedule. Specifically, you are directed to adhere to the following requirements:</p>
    <div class="body">
        <ol>
            <li>Report to work on time and remain on duty for your entire scheduled shift.</li>
            <li>Unexcused absences will not be tolerated. All absences must be properly requested, approved in advance, and supported by required documentation when applicable. In the event of an emergency that prevents timely reporting, you must notify your immediate supervisor and the HR Department prior to the start of your shift (or as soon as reasonably possible) and submit appropriate documentation upon your return.</li>
            <li>Any additional instances of tardiness, unapproved absences, or failure to follow established attendance and reporting procedures may result in further disciplinary action, up to and including a final written warning, suspension, or termination, in accordance with company policy and due process.</li>
        </ol>
    </div>
    <p class="body">You are required to meet with the Human Resources Department within five (5) working days of receipt of this memorandum to review expectations, corrective measures, and monitoring procedures.</p>
    <p class="body">For your compliance.</p>
    <p class="sign">{{ $fields['from_name'] ?? '' }}<br>{{ $fields['from_title'] ?? '' }}</p>
</div>
</body>
</html>
