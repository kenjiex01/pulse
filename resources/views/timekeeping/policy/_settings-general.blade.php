@php
    $validateRestDays = (bool) old('enable_employee_validation_for_rest_days', $policy->enable_employee_validation_for_rest_days);
@endphp

<form
    method="POST"
    action="{{ route(\App\Support\TimekeepingPolicy::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'general']) }}"
    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6"
    data-timekeeping-settings="general"
>
    @csrf
    @method('PUT')

    <div>
        <h3 class="text-sm font-semibold text-gray-900">General</h3>
        <div class="mt-3 space-y-4">
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="enable_attendance_approval" value="0">
                <input type="checkbox" name="enable_attendance_approval" value="1" @checked(old('enable_attendance_approval', $policy->enable_attendance_approval))>
                Enable Employee Attendance Approval
            </label>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="buffer_time_in" class="form-label">Buffer time for Time In (hours) <span class="text-red-500">*</span></label>
                    <input id="buffer_time_in" name="buffer_time_in" type="number" step="0.01" min="0" value="{{ old('buffer_time_in', $policy->buffer_time_in) }}" class="form-input" required>
                    @error('buffer_time_in')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="buffer_time_out" class="form-label">Buffer Time for Time Out (hours) <span class="text-red-500">*</span></label>
                    <input id="buffer_time_out" name="buffer_time_out" type="number" step="0.01" min="0" value="{{ old('buffer_time_out', $policy->buffer_time_out) }}" class="form-input" required>
                    @error('buffer_time_out')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="max-w-md">
                <label for="non_regular_hours_computation_basis" class="form-label">Non Regular Hours Computation Basis <span class="text-red-500">*</span></label>
                <select id="non_regular_hours_computation_basis" name="non_regular_hours_computation_basis" class="form-input" required>
                    <option value="">Select basis</option>
                    @foreach ($selectOptions['non_regular_hours_bases'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) old('non_regular_hours_computation_basis', $policy->non_regular_hours_computation_basis) === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('non_regular_hours_computation_basis')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="enable_employee_validation_for_rest_days" value="0">
                <input type="checkbox" name="enable_employee_validation_for_rest_days" value="1" data-rest-day-toggle @checked($validateRestDays)>
                Enable Employee Validation for Rest Days
            </label>

            <div class="grid gap-4 md:grid-cols-2" data-rest-day-panel @class(['opacity-50' => ! $validateRestDays])>
                <div>
                    <label for="max_rest_days_per_week" class="form-label">Maximum no. of rest days per week <span class="text-red-500">*</span></label>
                    <input
                        id="max_rest_days_per_week"
                        name="max_rest_days_per_week"
                        type="number"
                        min="0"
                        max="7"
                        step="1"
                        value="{{ old('max_rest_days_per_week', $policy->max_rest_days_per_week) }}"
                        class="form-input"
                        data-rest-day-field
                        @disabled(! $validateRestDays)
                    >
                    @error('max_rest_days_per_week')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="min_hours_rendered_per_week" class="form-label">Minimum no. of hours rendered per week (hours) <span class="text-red-500">*</span></label>
                    <input
                        id="min_hours_rendered_per_week"
                        name="min_hours_rendered_per_week"
                        type="number"
                        step="0.0001"
                        min="0"
                        value="{{ old('min_hours_rendered_per_week', $policy->min_hours_rendered_per_week) }}"
                        class="form-input"
                        data-rest-day-field
                        @disabled(! $validateRestDays)
                    >
                    @error('min_hours_rendered_per_week')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
    </div>

    @can('timekeeping-policy.update')
        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button type="submit" class="btn-primary">Save Settings</button>
        </div>
    @endcan
</form>
