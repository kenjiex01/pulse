<?php

namespace App\Http\Controllers;

use App\Models\HrProbationaryEndNotificationLog;
use App\Models\HrSetupSetting;
use App\Services\ProbationaryEndNotificationScheduleService;
use App\Services\SysLogService;
use App\Support\HrSetup;
use App\Support\OutboundMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrSetupController extends Controller
{
    public function index(): View
    {
        HrSetup::authorize(auth()->user(), 'view');

        $settings = HrSetupSetting::settings();

        SysLogService::record(
            action: 'read',
            table: 'tbl_hr_setup_settings',
            recordId: $settings->hr_setup_setting_id,
            description: 'Opened HR Setup',
        );

        $activeTab = request()->query('tab') === 'history' ? 'history' : 'settings';

        $notificationHistory = HrProbationaryEndNotificationLog::query()
            ->with('employee')
            ->where('recipient_type', HrProbationaryEndNotificationLog::RECIPIENT_HR)
            ->orderByDesc('sent_at')
            ->orderByDesc('hr_probationary_end_notification_log_id')
            ->paginate(25)
            ->withQueryString();

        return view('hr.setup.index', [
            'settings' => $settings,
            'activeTab' => $activeTab,
            'notificationHistory' => $notificationHistory,
            'probationaryEndRunToday' => app(ProbationaryEndNotificationScheduleService::class)->todayRunStatus(),
            'mailDeliversToInbox' => OutboundMail::deliversToInbox(),
            'mailDriver' => OutboundMail::defaultMailer(),
            'mailSetupHint' => OutboundMail::setupHint(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        HrSetup::authorize($request->user(), 'update');

        $validated = $request->validate([
            'hr_email' => ['nullable', 'email', 'max:255'],
            'probationary_end_email_subject' => ['nullable', 'string', 'max:255'],
            'probationary_end_email_body' => ['nullable', 'string', 'max:10000'],
            'probationary_end_notification_days' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    foreach (preg_split('/\s*,\s*/', trim($value)) ?: [] as $part) {
                        if ($part === '' || ! ctype_digit($part)) {
                            $fail('Enter day counts as comma-separated numbers (e.g. 7, 14, 30).');

                            return;
                        }

                        $days = (int) $part;

                        if ($days < 0 || $days > 365) {
                            $fail('Each day value must be between 0 and 365.');

                            return;
                        }
                    }
                },
            ],
        ]);

        $notificationDaysRaw = $validated['probationary_end_notification_days'] ?? null;
        $notificationDaysNormalized = HrSetupSetting::normalizeProbationaryNotificationDaysInput(
            is_string($notificationDaysRaw) ? $notificationDaysRaw : null,
        );

        if ($notificationDaysNormalized !== null) {
            $subject = trim((string) ($validated['probationary_end_email_subject'] ?? ''));
            $body = trim((string) ($validated['probationary_end_email_body'] ?? ''));
            $hrEmailInput = trim((string) ($validated['hr_email'] ?? ''));

            if ($subject === '' || $body === '' || $hrEmailInput === '') {
                $errors = [];

                if ($hrEmailInput === '') {
                    $errors['hr_email'] = 'HR email is required when probationary notification days are configured.';
                }

                if ($subject === '') {
                    $errors['probationary_end_email_subject'] = 'Email subject is required when notification days are configured.';
                }

                if ($body === '') {
                    $errors['probationary_end_email_body'] = 'Email body is required when notification days are configured.';
                }

                return back()
                    ->withInput()
                    ->withErrors($errors);
            }
        }

        $settings = HrSetupSetting::settings();
        $oldValues = [
            'hr_email' => $settings->hr_email,
            'probationary_end_notification_days' => $settings->probationary_end_notification_days,
            'probationary_end_email_subject' => $settings->probationary_end_email_subject,
            'probationary_end_email_body' => $settings->probationary_end_email_body,
        ];
        $hrEmail = filled($validated['hr_email'] ?? null)
            ? strtolower(trim((string) $validated['hr_email']))
            : null;
        $notificationDays = $notificationDaysNormalized;

        $settings->update([
            'hr_email' => $hrEmail,
            'probationary_end_notification_days' => $notificationDays,
            'probationary_end_email_subject' => filled($validated['probationary_end_email_subject'] ?? null)
                ? trim((string) $validated['probationary_end_email_subject'])
                : null,
            'probationary_end_email_body' => filled($validated['probationary_end_email_body'] ?? null)
                ? trim((string) $validated['probationary_end_email_body'])
                : null,
        ]);

        SysLogService::record(
            action: 'update',
            table: 'tbl_hr_setup_settings',
            recordId: $settings->hr_setup_setting_id,
            oldValues: $oldValues,
            newValues: [
                'hr_email' => $settings->hr_email,
                'probationary_end_notification_days' => $settings->probationary_end_notification_days,
                'probationary_end_email_subject' => $settings->probationary_end_email_subject,
                'probationary_end_email_body' => $settings->probationary_end_email_body,
            ],
            description: 'Updated HR Setup',
        );

        return redirect()
            ->route(HrSetup::routeName('index'))
            ->with('success', 'HR Setup saved successfully.');
    }
}
