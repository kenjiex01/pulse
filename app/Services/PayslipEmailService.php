<?php

namespace App\Services;

use App\Mail\PayslipMail;
use App\Models\Employee;
use App\Models\User;
use App\Services\Reports\PayslipReportService;
use App\Support\PayslipFilename;
use App\Support\PayslipPdfProtector;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PayslipEmailService
{
    public function __construct(
        private readonly PayslipReportService $payslipReport,
        private readonly PayslipPdfProtector $pdfProtector,
    ) {}

    /**
     * @return array{success: bool, employee_id: int, employee_number: string, employee_name: string, email: string}
     */
    public function sendOne(int $batchId, int $employeeId, User $user): array
    {
        $this->ensureMailIsConfigured();

        $payslip = $this->payslipReport->buildPayslipForEmployee($batchId, $employeeId, $user);

        $employee = Employee::query()->find($employeeId);
        $email = trim((string) ($employee?->email ?? ''));

        if ($email === '') {
            throw ValidationException::withMessages([
                'employee_id' => 'Employee has no email address on file.',
            ]);
        }

        $pdfPassword = PayslipPdfProtector::passwordFromBirthDate($employee?->birth_date);
        if ($pdfPassword === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'Employee has no birth date on file. Required for payslip PDF password.',
            ]);
        }

        $pdf = $this->payslipReport->renderPayslipPdf($payslip);
        $pdf = $this->pdfProtector->protect($pdf, $pdfPassword);
        $filename = PayslipFilename::forPayslip($payslip);

        Mail::to($email)->send(new PayslipMail($payslip, $pdf, $filename));

        SysLogService::record(
            action: 'create',
            table: 'trn_payroll_batches',
            recordId: $batchId,
            description: 'Sent payslip email to '.$payslip['employee_name']
                .' ('.$payslip['employee_number'].') · '.$email,
        );

        return [
            'success' => true,
            'employee_id' => $employeeId,
            'employee_number' => (string) ($payslip['employee_number'] ?? ''),
            'employee_name' => (string) ($payslip['employee_name'] ?? ''),
            'email' => $email,
        ];
    }

    private function ensureMailIsConfigured(): void
    {
        $mailer = config('mail.default');

        if ($mailer === 'ses') {
            if (filled(config('services.ses.key')) && filled(config('services.ses.secret'))) {
                return;
            }

            throw ValidationException::withMessages([
                'mail' => 'Email is not configured. Set AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY in pulse/.env (or leave empty to reuse DB_BACKUP_S3_* keys), then restart the app.',
            ]);
        }

        if ($mailer !== 'smtp') {
            return;
        }

        if (filled(config('mail.mailers.smtp.password')) && filled(config('mail.mailers.smtp.username'))) {
            return;
        }

        throw ValidationException::withMessages([
            'mail' => 'Email is not configured. Set MAIL_USERNAME and MAIL_PASSWORD (Google App Password) in pulse/.env, then restart the app.',
        ]);
    }
}
