<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $preview['title'] ?? 'Report' }}</title>
    <style>
        @php
            $layout = $preview['meta']['layout'] ?? null;
            $isPayrollRegister = $layout === 'icct_per_hour';
            $isPayslip = $layout === 'payslip';
            $isBir2316 = $layout === 'bir_2316';
            $isAttendanceView = $layout === 'attendance_view';
        @endphp

        @if ($isPayrollRegister)
        @page {
            margin: 8mm 6mm;
        }
        @elseif ($isPayslip)
        @page {
            size: A4 landscape;
            margin: 10mm 12mm;
        }
        @elseif ($isAttendanceView)
        @page {
            size: A4 landscape;
            margin: 10mm 8mm;
        }
        @elseif ($isBir2316)
        @page {
            size: A4 portrait;
            margin: 6mm 5mm;
        }
        @else
        @page {
            margin: 16mm 12mm;
        }
        @endif

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: {{ $isPayrollRegister ? '6px' : ($isBir2316 ? '7px' : '11px') }};
        }

        @if ($isPayrollRegister)
        .payroll-register-pdf-wrap {
            width: 100%;
        }

        .payroll-register-pdf-wrap table {
            width: auto !important;
            max-width: none !important;
            border-collapse: collapse;
            table-layout: auto;
            font-size: 5.5px;
            line-height: 1.15;
            color: #111;
        }

        .payroll-register-pdf-wrap th,
        .payroll-register-pdf-wrap td {
            border: 0.4px solid #999;
            padding: 1px 2px;
            white-space: nowrap;
            word-break: keep-all;
            overflow: hidden;
            vertical-align: middle;
        }

        .payroll-register-pdf-wrap th {
            font-weight: 700;
            text-align: center;
            background: #f3f4f6;
        }
        @endif
    </style>
</head>
<body>
    @include('payroll.reports._report-document-body', ['preview' => $preview])
</body>
</html>
