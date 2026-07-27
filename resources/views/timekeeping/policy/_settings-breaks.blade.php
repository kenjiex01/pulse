@php
    $deductBreakTardiness = old('break_deduct_tardiness') !== null
        ? filter_var(old('break_deduct_tardiness'), FILTER_VALIDATE_BOOLEAN)
        : (bool) $policy->break_deduct_tardiness;
@endphp

<form
    method="POST"
    action="{{ route(\App\Support\TimekeepingPolicy::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'breaks']) }}"
    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6"
    data-timekeeping-settings="breaks"
>
    @csrf
    @method('PUT')

    <div>
        <h3 class="text-sm font-semibold text-gray-900">Breaks</h3>
        <div class="mt-3 space-y-3">
            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="break_computation" value="1" @checked((string) old('break_computation', $policy->break_computation) === '1') required>
                    Use scheduled break time in and out
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="break_computation" value="2" @checked((string) old('break_computation', $policy->break_computation) === '2')>
                    Use actual break time in and out
                </label>
                @error('break_computation')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="break_deduct_tardiness" value="0">
                <input type="checkbox" name="break_deduct_tardiness" value="1" data-break-tardiness-toggle @checked($deductBreakTardiness)>
                Deduct Tardiness in Breaks
            </label>
            <div class="rounded-lg border border-gray-200 p-4" data-break-tardiness-panel>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="break_grace_period" class="form-label">Grace Period (minutes)</label>
                        <input id="break_grace_period" name="break_grace_period" type="number" step="0.0001" min="0" value="{{ old('break_grace_period', $policy->break_grace_period) }}" class="form-input" data-break-tardiness-field>
                        <label class="mt-2 flex items-center gap-2 text-sm">
                            <input type="hidden" name="is_break_deduct_grace_period" value="0">
                            <input type="checkbox" name="is_break_deduct_grace_period" value="1" @checked(old('is_break_deduct_grace_period', $policy->is_break_deduct_grace_period)) data-break-tardiness-field>
                            Actual Late less Grace Period
                        </label>
                        @error('break_grace_period')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="break_tardiness_rounding_id" class="form-label">Rounding Rule</label>
                        <select id="break_tardiness_rounding_id" name="break_tardiness_rounding_id" class="form-input" data-break-tardiness-field>
                            <option value="">Select rounding</option>
                            @foreach ($selectOptions['rounding'] as $value => $label)
                                <option value="{{ $value }}" @selected((string) old('break_tardiness_rounding_id', $policy->break_tardiness_rounding_id) === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="break_tardiness_leave_type_id" class="form-label">Leave Type</label>
                        <select id="break_tardiness_leave_type_id" name="break_tardiness_leave_type_id" class="form-input" data-break-tardiness-field>
                            <option value="">Select leave type</option>
                            @foreach ($selectOptions['leave_types'] as $value => $label)
                                <option value="{{ $value }}" @selected((string) old('break_tardiness_leave_type_id', $policy->break_tardiness_leave_type_id) === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('break_tardiness_leave_type_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
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
