@php
    $employeeName = (string) ($payslip['employee_name'] ?? 'Employee');
    $payPeriod = (string) ($payslip['pay_period'] ?? '');
@endphp

<x-mail::message>
# Payslip

Hello **{{ $employeeName }}**,

@if ($payPeriod !== '')
Your payslip for **{{ $payPeriod }}** is attached to this email.
@else
Your payslip is attached to this email.
@endif

If you have questions about your payslip, please contact your HR or payroll office.

Thanks,<br>
{{ config('payslip_report.company_name', config('app.name')) }}
</x-mail::message>
