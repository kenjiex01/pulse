<?php

namespace App\Services;

use App\Mail\BiometricMissingUploadHrNotificationMail;
use App\Models\HrSetupSetting;
use App\Support\OutboundMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class BiometricMissingUploadHrNotificationService
{
    public function __construct(
        private readonly BiometricCollectorDashboardService $dashboardService,
    ) {}

    /**
     * @return array{
     *     sent: bool,
     *     skipped_reason: string|null,
     *     missing_campus_count: int,
     *     active_campus_count: int,
     *     hr_email: string|null,
     * }
     */
    public function sendDailyReport(?Carbon $onDate = null): array
    {
        $onDate = ($onDate ?? Carbon::now('Asia/Manila'))->copy()->timezone('Asia/Manila')->startOfDay();

        $result = [
            'sent' => false,
            'skipped_reason' => null,
            'missing_campus_count' => 0,
            'active_campus_count' => 0,
            'hr_email' => null,
        ];

        $hrEmails = HrSetupSetting::hrEmails();

        if ($hrEmails === []) {
            $result['skipped_reason'] = 'hr_email_not_configured';

            return $result;
        }

        $result['hr_email'] = implode(', ', $hrEmails);

        Cache::forget('biometric_collector_dashboard_'.$onDate->toDateString());
        $status = $this->dashboardService->statusForDate($onDate);

        if (! ($status['configured'] ?? false)) {
            $result['skipped_reason'] = 'biometric_s3_not_configured';

            return $result;
        }

        if (filled($status['error'] ?? null)) {
            $result['skipped_reason'] = 'biometric_s3_read_error';

            return $result;
        }

        /** @var array<int, array<string, mixed>> $missing */
        $missing = $status['missing_today'] ?? [];
        /** @var array<int, array<string, mixed>> $collected */
        $collected = $status['collected_today'] ?? [];

        $result['missing_campus_count'] = count($missing);
        $result['active_campus_count'] = count($missing) + count($collected);

        $missingCampuses = collect($missing)
            ->map(fn (array $row): array => [
                'campus_name' => (string) ($row['campus_name'] ?? ''),
                'campus_code' => (string) ($row['campus_code'] ?? ''),
            ])
            ->values()
            ->all();

        try {
            $this->ensureMailIsConfigured();

            Mail::to($hrEmails)->send(new BiometricMissingUploadHrNotificationMail(
                (string) ($status['reference_date_label'] ?? $onDate->format('F j, Y')),
                $missingCampuses,
                $result['active_campus_count'],
            ));

            $result['sent'] = true;
        } catch (Throwable $exception) {
            Log::warning('Biometric missing upload HR notification failed.', [
                'message' => $exception->getMessage(),
            ]);
            $result['skipped_reason'] = 'mail_failed';
        }

        return $result;
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
