<?php

namespace App\Services;

use App\Mail\ProbationaryEndNotificationMail;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\HrProbationaryEndNotificationLog;
use App\Models\HrSetupSetting;
use App\Support\OutboundMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProbationaryEndNotificationService
{
    public function __construct(
        private readonly CompanyDocumentMergeTagService $mergeTagService,
    ) {}

    /**
     * @return array{employees_checked: int, employee_emails_sent: int, hr_emails_sent: int, skipped_already_sent: int}
     */
    public function sendDueNotifications(?Carbon $today = null): array
    {
        $todayDate = ($today ?? Carbon::now('Asia/Manila'))->copy()->timezone('Asia/Manila')->toDateString();
        $settings = HrSetupSetting::settings();
        $offsets = HrSetupSetting::probationaryEndNotificationDayList();

        $stats = [
            'employees_checked' => 0,
            'employee_emails_sent' => 0,
            'hr_emails_sent' => 0,
            'skipped_already_sent' => 0,
        ];

        $hrEmail = HrSetupSetting::hrEmail();
        $subjectTemplate = trim((string) ($settings->probationary_end_email_subject ?? ''));
        $bodyTemplate = trim((string) ($settings->probationary_end_email_body ?? ''));

        if ($offsets === [] || $hrEmail === null || ! $this->templatesAreConfigured($settings)) {
            return $stats;
        }

        Employee::query()
            ->where('employment_status', Employee::STATUS_ACTIVE)
            ->where('is_active', true)
            ->with(['employmentInformations' => function ($query): void {
                $query->orderBy('sort_order')->orderBy('employment_info_id');
            }])
            ->orderBy('employee_id')
            ->chunkById(100, function ($employees) use (
                $todayDate,
                $offsets,
                $hrEmail,
                $subjectTemplate,
                $bodyTemplate,
                &$stats,
            ): void {
                foreach ($employees as $employee) {
                    $employment = $this->primaryEmployment($employee);

                    if ($employment === null || ! $employment->isProbationaryEmployment()) {
                        continue;
                    }

                    if ($employment->probationary_end_date === null) {
                        continue;
                    }

                    $endDate = Carbon::parse($employment->probationary_end_date)->timezone('Asia/Manila')->startOfDay();
                    $endDateString = $endDate->toDateString();

                    if ($endDateString <= $todayDate) {
                        continue;
                    }

                    $stats['employees_checked']++;

                    foreach ($offsets as $daysBefore) {
                        $notifyDate = $endDate->copy()->subDays($daysBefore)->toDateString();

                        if ($notifyDate !== $todayDate) {
                            continue;
                        }

                        if (HrProbationaryEndNotificationLog::wasSent(
                            (int) $employee->employee_id,
                            $endDateString,
                            $daysBefore,
                            HrProbationaryEndNotificationLog::RECIPIENT_HR,
                        )) {
                            $stats['skipped_already_sent']++;

                            continue;
                        }

                        $context = [
                            'probationary_end_date' => $endDate->format('F j, Y'),
                            'days_before_end' => (string) $daysBefore,
                        ];

                        $sent = $this->trySendHrEmail(
                            $employee,
                            $hrEmail,
                            $subjectTemplate,
                            $bodyTemplate,
                            $context,
                        );

                        if ($sent !== null) {
                            $this->recordSent(
                                (int) $employee->employee_id,
                                $endDateString,
                                $daysBefore,
                                HrProbationaryEndNotificationLog::RECIPIENT_HR,
                                $sent,
                            );
                            $stats['hr_emails_sent']++;
                        }
                    }
                }
            }, 'employee_id');

        return $stats;
    }

    private function templatesAreConfigured(HrSetupSetting $settings): bool
    {
        return trim((string) ($settings->probationary_end_email_subject ?? '')) !== ''
            && trim((string) ($settings->probationary_end_email_body ?? '')) !== '';
    }

    private function primaryEmployment(Employee $employee): ?EmployeeEmploymentInformation
    {
        if ($employee->relationLoaded('employmentInformations')) {
            return $employee->employmentInformations->first();
        }

        return $employee->employmentInformations()->first();
    }

    /**
     * @param  array{probationary_end_date: string, days_before_end: string}  $context
     */
    /**
     * @param  array{probationary_end_date: string, days_before_end: string}  $context
     * @return array{hr_email_to: string, email_subject: string, email_body: string}|null
     */
    private function trySendHrEmail(
        Employee $employee,
        string $hrEmail,
        string $subjectTemplate,
        string $bodyTemplate,
        array $context,
    ): ?array {
        try {
            $this->ensureMailIsConfigured();

            $subject = $this->mergeTagService->resolveInlineTags($subjectTemplate, $employee, $context);
            $body = $this->mergeTagService->resolveInlineTags($bodyTemplate, $employee, $context);

            Mail::to($hrEmail)->send(new ProbationaryEndNotificationMail($subject, $body));

            return [
                'hr_email_to' => $hrEmail,
                'email_subject' => $subject,
                'email_body' => $body,
            ];
        } catch (Throwable $exception) {
            Log::warning('Probationary end HR email failed.', [
                'employee_id' => $employee->employee_id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array{hr_email_to: string, email_subject: string, email_body: string}  $emailSnapshot
     */
    private function recordSent(
        int $employeeId,
        string $endDate,
        int $daysBefore,
        string $recipientType,
        array $emailSnapshot,
    ): void {
        HrProbationaryEndNotificationLog::query()->create([
            'employee_id' => $employeeId,
            'probationary_end_date' => $endDate,
            'days_before' => $daysBefore,
            'recipient_type' => $recipientType,
            'hr_email_to' => $emailSnapshot['hr_email_to'],
            'email_subject' => $emailSnapshot['email_subject'],
            'email_body' => $emailSnapshot['email_body'],
            'sent_at' => now(),
        ]);
    }

    private function ensureMailIsConfigured(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (OutboundMail::deliversToInbox()) {
            return;
        }

        throw ValidationException::withMessages([
            'mail' => OutboundMail::setupHint(),
        ]);
    }
}
