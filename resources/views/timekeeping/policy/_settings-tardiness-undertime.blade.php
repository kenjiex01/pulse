@php
    $flexiEnabled = filter_var(old('is_allow_flexi_time', $policy->is_allow_flexi_time ?? false), FILTER_VALIDATE_BOOLEAN);
    $maxFlexiValue = $flexiEnabled ? old('max_flexi_time', $policy->max_flexi_time) : '';
@endphp

<form
    method="POST"
    action="{{ route(\App\Support\TimekeepingPolicy::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'tardiness-undertime']) }}"
    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6"
    data-timekeeping-settings="tardiness-undertime"
>
    @csrf
    @method('PUT')

    {{-- Preserve offset setting when saving general tardiness/undertime fields (checkbox lives on the equivalents card). --}}
    <input type="hidden" name="is_offset_absent_tardiness_with_ot" value="{{ old('is_offset_absent_tardiness_with_ot', $policy->is_offset_absent_tardiness_with_ot) ? '1' : '0' }}">

    <p class="text-sm text-gray-500">
        This policy applies to all employees tagged as compute late and/or compute undertime under the Employees module.
    </p>

    <div>
        <h3 class="text-sm font-semibold text-gray-900">Tardiness</h3>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            <div>
                <span class="form-label">Allow Flexi-time <span class="text-red-500">*</span></span>
                <div class="mt-2 flex gap-4">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="is_allow_flexi_time" value="1" data-flexi-toggle @checked($flexiEnabled)>
                        Yes
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="is_allow_flexi_time" value="0" data-flexi-toggle @checked(! $flexiEnabled)>
                        No
                    </label>
                </div>
                @error('is_allow_flexi_time')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div data-flexi-panel @class(['opacity-50' => ! $flexiEnabled])>
                <label for="max_flexi_time" class="form-label">Max. Flexi-time (minutes)</label>
                <input
                    id="max_flexi_time"
                    name="max_flexi_time"
                    type="number"
                    step="0.0001"
                    min="0"
                    value="{{ $maxFlexiValue }}"
                    class="form-input"
                    data-flexi-field
                    @disabled(! $flexiEnabled)
                >
                @error('max_flexi_time')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="grace_period" class="form-label">Grace Period (minutes)</label>
                @php
                    $gracePeriodInput = old('grace_period', $policy->grace_period);
                    if ($gracePeriodInput === null || $gracePeriodInput === '' || (is_numeric($gracePeriodInput) && (float) $gracePeriodInput <= 0)) {
                        $gracePeriodInput = '';
                    }
                @endphp
                <input
                    id="grace_period"
                    name="grace_period"
                    type="number"
                    step="0.0001"
                    min="0"
                    value="{{ $gracePeriodInput }}"
                    class="form-input"
                    placeholder="Leave blank if none"
                >
                <p class="mt-1 text-xs text-gray-500">Optional. Leave blank for no grace period.</p>
                <label class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="is_deduct_grace_period" value="0">
                    <input type="checkbox" name="is_deduct_grace_period" value="1" @checked(old('is_deduct_grace_period', $policy->is_deduct_grace_period))>
                    Actual Late less Grace Period
                </label>
                @error('grace_period')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="tardiness_rounding_id" class="form-label">Rounding Rule</label>
                <select id="tardiness_rounding_id" name="tardiness_rounding_id" class="form-input">
                    <option value="">Select rounding</option>
                    @foreach ($selectOptions['rounding'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) old('tardiness_rounding_id', $policy->tardiness_rounding_id) === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="tardiness_leave_type_id" class="form-label">Leave Type <span class="text-red-500">*</span></label>
                <select id="tardiness_leave_type_id" name="tardiness_leave_type_id" class="form-input" required>
                    <option value="">Select leave type</option>
                    @foreach ($selectOptions['leave_types'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) old('tardiness_leave_type_id', $policy->tardiness_leave_type_id) === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('tardiness_leave_type_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-gray-900">Undertime</h3>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            <div>
                <label for="undertime_rounding_id" class="form-label">Rounding Rule</label>
                <select id="undertime_rounding_id" name="undertime_rounding_id" class="form-input">
                    <option value="">Select rounding</option>
                    @foreach ($selectOptions['rounding'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) old('undertime_rounding_id', $policy->undertime_rounding_id) === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="undertime_leave_type_id" class="form-label">Leave Type <span class="text-red-500">*</span></label>
                <select id="undertime_leave_type_id" name="undertime_leave_type_id" class="form-input" required>
                    <option value="">Select leave type</option>
                    @foreach ($selectOptions['leave_types'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) old('undertime_leave_type_id', $policy->undertime_leave_type_id) === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('undertime_leave_type_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    @can('timekeeping-policy.update')
        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button type="submit" class="btn-primary">Save Settings</button>
        </div>
    @endcan
</form>

@include('timekeeping.policy._equivalent-section', [
    'policy' => $policy,
    'type' => 'tardiness',
    'records' => $equivalentRecords['tardiness'] ?? collect(),
])

<p class="mt-2 text-xs text-gray-500">
    Example: 1–5 min late → 5 min equivalent; 6–15 min → 15 min; 16 min and up → check <strong>Mark as absent</strong> (equivalent may be 0).
    Assign this policy group on each employee under Timekeeping → Employee Profile → Timekeeping Settings.
</p>
