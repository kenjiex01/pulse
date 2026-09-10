<?php

namespace App\Support;

class PayslipFilename
{
    /**
     * @param  array<string, mixed>  $payslip
     */
    public static function forPayslip(array $payslip): string
    {
        $parts = array_filter([
            trim((string) ($payslip['last_name'] ?? '')),
            trim((string) ($payslip['first_name'] ?? '')),
            trim((string) ($payslip['middle_name'] ?? '')),
            trim((string) ($payslip['pay_period'] ?? '')),
        ], fn (string $part) => $part !== '');

        $base = $parts !== [] ? implode(' ', $parts) : trim((string) ($payslip['employee_name'] ?? 'Payslip'));

        return self::sanitize($base).'.pdf';
    }

    /**
     * @param  array<string, mixed>  $payslip
     */
    public static function forPayslipWithSuffix(array $payslip, string $suffix): string
    {
        $filename = self::forPayslip($payslip);

        return str_replace('.pdf', " {$suffix}.pdf", $filename);
    }

    public static function sanitize(string $filename): string
    {
        $clean = preg_replace('/[\/\\\\:*?"<>|]+/', '', $filename) ?? $filename;
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

        return trim($clean) !== '' ? trim($clean) : 'Payslip';
    }
}
