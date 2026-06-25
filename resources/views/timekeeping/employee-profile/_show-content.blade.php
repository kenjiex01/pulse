@php
    use App\Support\TimekeepingEmployeeProfile;

    $setup = $employee->timekeepingSetup;
    $restDayMap = $employee->timekeepingRestDays->keyBy('day_id');
    $isComplete = TimekeepingEmployeeProfile::isSetupComplete($employee);
    $activeTab = TimekeepingEmployeeProfile::normalizeSetupTab(request('view_tab', 'timekeeping'));

    $attendanceLogs = \App\Models\RawTimekeepingInandout::query()
        ->where('employee_id', $employee->employee_id)
        ->orderByDesc('dt_datetime')
        ->orderByDesc('timekeeping_inandout_id')
        ->limit(50)
        ->get();
@endphp

<div class="space-y-4" data-employee-form-tabs>
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-sm font-medium text-gray-900">{{ $employee->full_name }}</span>
        <span class="text-sm text-gray-500">({{ $employee->employee_number }})</span>
        @if ($isComplete)
            <span class="badge-success">Setup Complete</span>
        @else
            <span class="badge-muted">Needs Setup</span>
        @endif
    </div>

    @include('timekeeping.employee-profile._setup-tabs-nav', ['activeTab' => $activeTab])

    <div class="employee-tab-panel {{ $activeTab === 'timekeeping' ? '' : 'hidden' }}" data-employee-tab-panel="timekeeping">
        @if (! $isComplete)
            <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                This employee has not been configured for timekeeping yet. Use <strong>Setup</strong> to assign holiday group, shift, and policy.
            </p>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <h4 class="mb-2 text-sm font-semibold text-gray-900">Rest Days</h4>
                @if ($restDayMap->isEmpty())
                    <p class="text-sm text-gray-500">No rest days configured.</p>
                @else
                    <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 text-sm">
                        @foreach ($formOptions['days'] as $day)
                            @php $restDay = $restDayMap->get($day->day_id); @endphp
                            @if ($restDay)
                                <li class="flex items-center justify-between px-3 py-2">
                                    <span class="text-gray-800">{{ $day->day }}</span>
                                    <span class="{{ $restDay->is_paid ? 'badge-success' : 'badge-muted' }}">
                                        {{ $restDay->is_paid ? 'Paid' : 'Unpaid' }}
                                    </span>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>

            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="font-medium text-gray-500">Holiday Group</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $setup?->holidayGroup?->description ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Shift Code</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $setup?->shiftCode?->description ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Policy Group</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $setup?->policy?->policy_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Leave Cancellation</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $setup?->is_leave ? 'Enabled' : 'Disabled' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Auto Populate Attendance</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $setup?->is_populate ? 'Enabled' : 'Disabled' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Team Subordinates Viewing Limit</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $setup?->teamSetting?->description ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    @if (TimekeepingEmployeeProfile::showApprovalMatrix())
        <div class="employee-tab-panel {{ $activeTab === 'approval' ? '' : 'hidden' }}" data-employee-tab-panel="approval">
            @include('timekeeping.employee-profile._tab-approval-settings', [
                'employee' => $employee,
                'formTypeId' => (int) request('form_type_id', 0),
            ])
        </div>
    @endif

    <div class="employee-tab-panel {{ $activeTab === 'attendance' ? '' : 'hidden' }}" data-employee-tab-panel="attendance">
        @include('timekeeping.employee-profile._tab-attendance-view', [
            'employee' => $employee,
            'attendanceLogs' => $attendanceLogs,
        ])
    </div>

    @can('employee-profile.update')
        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button
                type="button"
                class="btn-secondary"
                data-modal-close="employee-profile-view-{{ $employee->employee_id }}"
                data-modal-open="employee-profile-setup-{{ $employee->employee_id }}"
            >
                {{ $isComplete ? 'Edit Setup' : 'Setup Now' }}
            </button>
        </div>
    @endcan
</div>
