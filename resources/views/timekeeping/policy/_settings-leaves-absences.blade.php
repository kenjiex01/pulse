<form
    method="POST"
    action="{{ route(\App\Support\TimekeepingPolicy::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'leaves-absences']) }}"
    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6"
    data-timekeeping-settings="leaves-absences"
>
    @csrf
    @method('PUT')

    <div>
        <h3 class="text-sm font-semibold text-gray-900">Leaves and Absences</h3>
        <div class="mt-3 space-y-3">
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="hide_negative_leaves" value="0">
                <input type="checkbox" name="hide_negative_leaves" value="1" @checked(old('hide_negative_leaves', $policy->hide_negative_leaves))>
                Hide Negative Leaves
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="enable_notification" value="0">
                <input type="checkbox" name="enable_notification" value="1" data-notification-toggle @checked(old('enable_notification', $policy->enable_notification))>
                Enable Notification
            </label>
            <div>
                <label for="notif_for_process" class="form-label">Attendance Notification When Processing</label>
                <textarea
                    id="notif_for_process"
                    name="notif_for_process"
                    rows="5"
                    maxlength="500"
                    class="form-input"
                    data-notification-field
                    @disabled(! old('enable_notification', $policy->enable_notification))
                >{{ old('notif_for_process', $policy->notif_for_process) }}</textarea>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="awol_leave_type_id" class="form-label">Leave of Absence Type <span class="text-red-500">*</span></label>
            <select id="awol_leave_type_id" name="awol_leave_type_id" class="form-input" required>
                <option value="">Select leave type</option>
                @foreach ($selectOptions['leave_types'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('awol_leave_type_id', $policy->awol_leave_type_id) === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('awol_leave_type_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="leave_processing_mode" class="form-label">Leave Processing Mode <span class="text-red-500">*</span></label>
            <select id="leave_processing_mode" name="leave_processing_mode" class="form-input" required>
                <option value="">Select mode</option>
                @foreach ($selectOptions['leave_processing_modes'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('leave_processing_mode', $policy->leave_processing_mode) === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('leave_processing_mode')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="validity_of_late_file" class="form-label">Validity of Late File (days) <span class="text-red-500">*</span></label>
            <input id="validity_of_late_file" name="validity_of_late_file" type="number" min="0" value="{{ old('validity_of_late_file', $policy->validity_of_late_file ?? 30) }}" class="form-input" required>
            @error('validity_of_late_file')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    @can('timekeeping-policy.update')
        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button type="submit" class="btn-primary">Save Settings</button>
        </div>
    @endcan
</form>

<div class="mt-6 space-y-4 rounded-xl border border-gray-200 bg-white p-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Leave Equivalents</h3>
            <p class="text-sm text-gray-500">Leave types already used in policy settings are excluded.</p>
        </div>
        <form method="GET" action="{{ route(\App\Support\TimekeepingPolicy::routeName('tab'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'leaves-absences']) }}" class="min-w-[220px]">
            <label for="leave_type_id" class="form-label">Leave Type</label>
            <select id="leave_type_id" name="leave_type_id" class="form-input" onchange="this.form.submit()">
                @forelse ($availableLeaveTypes as $value => $label)
                    <option value="{{ $value }}" @selected((string) ($selectedLeaveTypeId ?? '') === (string) $value)>{{ $label }}</option>
                @empty
                    <option value="">No available leave types</option>
                @endforelse
            </select>
        </form>
    </div>

    @if ($selectedLeaveTypeId)
        @include('timekeeping.policy._equivalent-section', [
            'policy' => $policy,
            'type' => 'leave',
            'records' => $leaveEquivalents,
            'leaveTypeId' => $selectedLeaveTypeId,
        ])
    @endif
</div>
