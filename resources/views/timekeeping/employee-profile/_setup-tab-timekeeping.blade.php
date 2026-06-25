@php
    $setup = $employee->timekeepingSetup;
    $restDayMap = $employee->timekeepingRestDays->keyBy('day_id');
    $formContext = 'setup-employee-'.$employee->employee_id;
    $isOpenContext = old('form_context') === $formContext && $errors->any();
@endphp

<p class="text-sm text-gray-600">Please fill out all required (<span class="text-red-600">*</span>) fields.</p>

<div class="grid gap-6 lg:grid-cols-2">
    <div>
        <h4 class="mb-3 text-sm font-semibold text-gray-900">Rest Days</h4>
        <div class="overflow-hidden rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 w-12"></th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Day</th>
                        <th class="px-3 py-2 text-center font-medium text-gray-600 w-20">Paid?</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($formOptions['days'] as $day)
                        @php
                            $existingRestDay = $restDayMap->get($day->day_id);
                            $isSelected = $isOpenContext
                                ? ! empty(old("rest_days.{$day->day_id}.selected"))
                                : $existingRestDay !== null;
                            $isPaid = $isOpenContext
                                ? ! empty(old("rest_days.{$day->day_id}.is_paid"))
                                : (bool) ($existingRestDay?->is_paid);
                        @endphp
                        <tr>
                            <td class="px-3 py-2 text-center">
                                <input
                                    type="checkbox"
                                    name="rest_days[{{ $day->day_id }}][selected]"
                                    value="1"
                                    class="rounded border-gray-300 text-[#0B318F] focus:ring-[#0B318F]"
                                    data-rest-day-checkbox
                                    data-day-id="{{ $day->day_id }}"
                                    @checked($isSelected)
                                >
                            </td>
                            <td class="px-3 py-2 text-gray-800">{{ $day->day }}</td>
                            <td class="px-3 py-2 text-center">
                                <input
                                    type="checkbox"
                                    name="rest_days[{{ $day->day_id }}][is_paid]"
                                    value="1"
                                    class="rounded border-gray-300 text-[#0B318F] focus:ring-[#0B318F]"
                                    data-rest-day-paid="{{ $day->day_id }}"
                                    @checked($isPaid)
                                    @disabled(! $isSelected)
                                >
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-4">
        <div>
            <label for="holiday-group-{{ $employee->employee_id }}" class="form-label">Holiday Group <span class="text-red-600">*</span></label>
            <select
                id="holiday-group-{{ $employee->employee_id }}"
                name="timekeeping_holiday_group_id"
                class="form-input"
                required
            >
                <option value="">— Please select —</option>
                @foreach ($formOptions['holidayGroups'] as $group)
                    <option
                        value="{{ $group->timekeeping_holiday_group_id }}"
                        @selected((string) old('timekeeping_holiday_group_id', $setup?->timekeeping_holiday_group_id) === (string) $group->timekeeping_holiday_group_id)
                    >{{ $group->description }}</option>
                @endforeach
            </select>
            @error('timekeeping_holiday_group_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="shift-code-{{ $employee->employee_id }}" class="form-label">Shift Code <span class="text-red-600">*</span></label>
            <select
                id="shift-code-{{ $employee->employee_id }}"
                name="shift_code_id"
                class="form-input"
                required
            >
                <option value="">— Please select —</option>
                @foreach ($formOptions['shiftCodes'] as $shift)
                    <option
                        value="{{ $shift->shift_code_id }}"
                        @selected((string) old('shift_code_id', $setup?->shift_code_id) === (string) $shift->shift_code_id)
                    >{{ $shift->description }}</option>
                @endforeach
            </select>
            @error('shift_code_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="policy-group-{{ $employee->employee_id }}" class="form-label">Policy Group <span class="text-red-600">*</span></label>
            <select
                id="policy-group-{{ $employee->employee_id }}"
                name="timekeeping_policy_id"
                class="form-input"
                required
            >
                <option value="">— Please select —</option>
                @foreach ($formOptions['policies'] as $policy)
                    <option
                        value="{{ $policy->timekeeping_policy_id }}"
                        @selected((string) old('timekeeping_policy_id', $setup?->timekeeping_policy_id) === (string) $policy->timekeeping_policy_id)
                    >{{ $policy->policy_name }}</option>
                @endforeach
            </select>
            @error('timekeeping_policy_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-3 border-t border-gray-100 pt-4">
            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    name="is_leave"
                    value="1"
                    class="mt-0.5 rounded border-gray-300 text-[#0B318F] focus:ring-[#0B318F]"
                    @checked((bool) old('is_leave', $setup?->is_leave))
                >
                <span>Enable cancellation of leaves</span>
            </label>

            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    name="is_populate"
                    value="1"
                    class="mt-0.5 rounded border-gray-300 text-[#0B318F] focus:ring-[#0B318F]"
                    @checked((bool) old('is_populate', $setup?->is_populate))
                >
                <span>Auto populate attendance</span>
            </label>
        </div>

        <div>
            <label for="team-limit-{{ $employee->employee_id }}" class="form-label">Team Subordinates Viewing Limit</label>
            <select
                id="team-limit-{{ $employee->employee_id }}"
                name="timekeeping_policy_team_setting_id"
                class="form-input"
            >
                <option value="">— Please select —</option>
                @foreach ($formOptions['teamSettings'] as $teamSetting)
                    <option
                        value="{{ $teamSetting->timekeeping_policy_team_setting_id }}"
                        @selected((string) old('timekeeping_policy_team_setting_id', $setup?->timekeeping_policy_team_setting_id) === (string) $teamSetting->timekeeping_policy_team_setting_id)
                    >{{ $teamSetting->description }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

@include('partials.modal-form-actions', [
    'submitLabel' => 'Save Settings',
    'cancelModalId' => 'employee-profile-setup-'.$employee->employee_id,
])
