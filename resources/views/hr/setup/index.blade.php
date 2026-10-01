@extends('layouts.app')

@section('title', 'HR Setup — '.config('app.name'))

@section('content')
    @include('partials.flash')

    @include('partials.page-header', [
        'title' => 'HR Setup',
        'description' => 'Configure Human Resource settings used by notifications and workflows.',
    ])

    @php
        $activeTab = $activeTab ?? 'settings';
    @endphp

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" data-hr-setup-root>
        <div class="employee-salary-tab-bar mb-6" data-hr-setup-tabs>
            <button
                type="button"
                @class(['employee-salary-subtab-btn', 'employee-salary-subtab-btn-active' => $activeTab === 'settings'])
                data-hr-setup-tab="settings"
            >Settings</button>
            <button
                type="button"
                @class(['employee-salary-subtab-btn', 'employee-salary-subtab-btn-active' => $activeTab === 'history'])
                data-hr-setup-tab="history"
            >Notification history</button>
        </div>

        <div @class(['hidden' => $activeTab !== 'settings']) data-hr-setup-panel="settings">
            <form method="POST" action="{{ route(\App\Support\HrSetup::routeName('update')) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <section class="max-w-xl">
                    <h2 class="mb-4 text-base font-semibold text-gray-900">HR email</h2>
                    <div>
                        <label for="hr_email" class="form-label">HR email</label>
                        <input
                            type="text"
                            id="hr_email"
                            name="hr_email"
                            value="{{ old('hr_email', $settings->hr_email) }}"
                            class="form-input w-full"
                            maxlength="2000"
                            autocomplete="email"
                            placeholder="hr@example.com, hr.backup@example.com"
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            Used for HR-related email notifications. Enter one address or several separated by commas.
                        </p>
                        @error('hr_email')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </section>

                <section class="max-w-3xl border-t border-gray-100 pt-6">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <h2 class="text-base font-semibold text-gray-900">Probationary end notification</h2>
                        @if (filled($settings->probationary_end_notification_days))
                            @php
                                $runInboxDelivery = ($probationaryEndRunToday['mail_delivered_to_inbox'] ?? false) || $mailDeliversToInbox;
                            @endphp
                            @if ($probationaryEndRunToday['sent_today'] ?? false)
                                <div @class([
                                    'rounded-lg px-3 py-2 text-sm',
                                    'border border-green-200 bg-green-50 text-green-900' => $runInboxDelivery,
                                    'border border-amber-200 bg-amber-50 text-amber-900' => ! $runInboxDelivery,
                                ])>
                                    <p class="font-medium">
                                        @if ($runInboxDelivery)
                                            <span class="badge-success mr-1">Sent for today</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Ran today — not delivered to inbox</span>
                                        @endif
                                        @if (filled($probationaryEndRunToday['completed_at'] ?? null))
                                            {{ \Illuminate\Support\Carbon::parse($probationaryEndRunToday['completed_at'])->timezone('Asia/Manila')->format('M j, Y g:i A') }}
                                        @else
                                            {{ \Illuminate\Support\Carbon::now('Asia/Manila')->format('M j, Y') }}
                                        @endif
                                    </p>
                                    <p @class([
                                        'mt-1 text-xs',
                                        'text-green-800' => $runInboxDelivery,
                                        'text-amber-800' => ! $runInboxDelivery,
                                    ])>
                                        {{ (int) ($probationaryEndRunToday['hr_emails_sent'] ?? 0) }} HR email(s) sent
                                        @if ((int) ($probationaryEndRunToday['employees_checked'] ?? 0) > 0)
                                            · {{ (int) $probationaryEndRunToday['employees_checked'] }} probationary employee(s) checked
                                        @endif
                                        @if (! $runInboxDelivery)
                                            · mail driver: {{ $probationaryEndRunToday['mail_mailer'] ?? $mailDriver }}
                                        @endif
                                    </p>
                                    @if (! $runInboxDelivery)
                                        <p class="mt-2 text-xs text-amber-900">{{ $mailSetupHint }}</p>
                                        <p class="mt-1 text-xs text-amber-800">After fixing mail settings, run: <code class="rounded bg-amber-100 px-1 text-[11px]">php artisan hr:reset-probationary-end-notification-run-today</code> then open the app again (or run the send command).</p>
                                    @endif
                                </div>
                            @else
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                    <p class="font-medium"><span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Not sent yet today</span></p>
                                    <p class="mt-1 text-xs text-amber-800">Opens automatically on first use of People360 today (Asia/Manila).</p>
                                </div>
                            @endif
                        @endif
                    </div>
                    @if (filled($settings->probationary_end_notification_days) && ! $mailDeliversToInbox)
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                            <p class="font-medium">Outbound email is not configured for inbox delivery (driver: {{ $mailDriver }}).</p>
                            <p class="mt-1 text-xs">{{ $mailSetupHint }}</p>
                        </div>
                    @endif
                    <div>
                        <label for="probationary_end_notification_days" class="form-label">
                            Notification days before probationary ends
                        </label>
                        <input
                            type="text"
                            id="probationary_end_notification_days"
                            name="probationary_end_notification_days"
                            value="{{ old('probationary_end_notification_days', $settings->probationary_end_notification_days) }}"
                            class="form-input w-full max-w-md"
                            maxlength="255"
                            placeholder="e.g. 7, 14, 30"
                            inputmode="numeric"
                            autocomplete="off"
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            Comma-separated day counts before an employee’s probationary period ends (e.g. 7, 14, 30). Leave blank to disable.
                            Reminders run automatically the first time People360 is opened or used each calendar day (Asia/Manila). If reminders already ran today, they will not run again until tomorrow.
                        </p>
                        @error('probationary_end_notification_days')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="probationary_end_email_subject" class="form-label">HR email subject</label>
                            <input
                                type="text"
                                id="probationary_end_email_subject"
                                name="probationary_end_email_subject"
                                value="{{ old('probationary_end_email_subject', $settings->probationary_end_email_subject) }}"
                                class="form-input w-full max-w-3xl"
                                maxlength="255"
                                placeholder="Probationary period ending — @{{employee_full_name}}"
                            >
                            @error('probationary_end_email_subject')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="probationary_end_email_body" class="form-label">HR email body</label>
                            <textarea
                                id="probationary_end_email_body"
                                name="probationary_end_email_body"
                                rows="1"
                                class="form-input form-textarea-auto-grow w-full max-w-3xl"
                                data-textarea-auto-grow
                                maxlength="10000"
                                placeholder="Dear @{{employee_full_name}},&#10;&#10;Your probationary period ends on @{{probationary_end_date}} (@{{days_before_end}} day(s) from this reminder)."
                            >{{ old('probationary_end_email_body', $settings->probationary_end_email_body) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">
                                Sent to the HR email address above for each probationary employee on the matching day offset (once per employee and offset).
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-x-1 gap-y-1 text-xs text-gray-600">
                                <span class="font-medium text-gray-700">Insert tag into email body:</span>
                                @foreach ([
                                    ['key' => 'employee_full_name', 'label' => 'Employee full name'],
                                    ['key' => 'probationary_end_date', 'label' => 'Probationary end date'],
                                    ['key' => 'days_before_end', 'label' => 'Days before end'],
                                ] as $tag)
                                    <button
                                        type="button"
                                        class="font-medium text-[#00A3E6] hover:underline"
                                        data-hr-setup-insert-tag="{{ $tag['key'] }}"
                                    >{{ $tag['label'] }}</button>@if (! $loop->last)<span class="text-gray-400">·</span>@endif
                                @endforeach
                            </div>
                            @error('probationary_end_email_body')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                @can('hr-setup.update')
                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <button type="submit" class="btn-primary">Save</button>
                    </div>
                @endcan
            </form>
        </div>

        <div @class(['hidden' => $activeTab !== 'history']) data-hr-setup-panel="history">
            @include('hr.setup._notification-history', ['notificationHistory' => $notificationHistory])
        </div>
    </div>
@endsection
