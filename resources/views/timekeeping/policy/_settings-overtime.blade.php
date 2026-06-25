<form
    method="POST"
    action="{{ route(\App\Support\TimekeepingPolicy::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'overtime']) }}"
    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6"
    data-timekeeping-settings="overtime"
>
    @csrf
    @method('PUT')

    <p class="text-sm text-gray-500">Set a default policy for Overtime and Special Overtime.</p>

    @php
        $disregardExcess = (int) old('excess_hour_id', $policy->excess_hour_id) === \App\Support\TimekeepingPolicy::EXCESS_HOUR_DISREGARD;
    @endphp

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="excess_hour_id" class="form-label">Treatment of Excess Hours <span class="text-red-500">*</span></label>
            <select id="excess_hour_id" name="excess_hour_id" class="form-input" data-excess-hour-select required>
                <option value="">Select treatment</option>
                @foreach ($selectOptions['excess_hours'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('excess_hour_id', $policy->excess_hour_id) === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('excess_hour_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div data-ot-field @class(['opacity-50' => $disregardExcess])>
            <span class="form-label">Non-Regular Days</span>
            <div class="mt-2 space-y-2">
                @foreach ($selectOptions['non_regular_ot'] as $value => $label)
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input
                            type="radio"
                            name="is_ot_form_required"
                            value="{{ $value }}"
                            @checked((string) old('is_ot_form_required', $policy->is_ot_form_required ?? 0) === (string) $value)
                            @disabled($disregardExcess)
                        >
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    <div data-ot-field @class(['opacity-50' => $disregardExcess])>
        <span class="form-label">Consider as OT</span>
        <div class="mt-2 flex flex-wrap gap-4">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="hidden" name="is_consider_before_time" value="0">
                <input type="checkbox" name="is_consider_before_time" value="1" @checked(old('is_consider_before_time', $policy->is_consider_before_time)) @disabled($disregardExcess)>
                Before time schedule
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="hidden" name="is_consider_after_time" value="0">
                <input type="checkbox" name="is_consider_after_time" value="1" @checked(old('is_consider_after_time', $policy->is_consider_after_time)) @disabled($disregardExcess)>
                After time schedule
            </label>
        </div>
        @error('is_consider_before_time')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-4 md:grid-cols-3" data-ot-field @class(['opacity-50' => $disregardExcess])>
        <div>
            <label for="min_minutes" class="form-label">Minimum no. of Minutes</label>
            <input id="min_minutes" name="min_minutes" type="number" step="0.0001" min="0" value="{{ old('min_minutes', $policy->min_minutes) }}" class="form-input" @disabled($disregardExcess)>
            @error('min_minutes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="overtime_rounding_id" class="form-label">Rounding Rule</label>
            <select id="overtime_rounding_id" name="overtime_rounding_id" class="form-input" @disabled($disregardExcess)>
                <option value="">Select rounding</option>
                @foreach ($selectOptions['rounding'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('overtime_rounding_id', $policy->overtime_rounding_id) === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="special_ot_start" class="form-label">Special OT Start (HH:MM)</label>
            <input id="special_ot_start" name="special_ot_start" type="text" maxlength="5" placeholder="22:00" value="{{ old('special_ot_start', $policy->special_ot_start) }}" class="form-input" @disabled($disregardExcess)>
            @error('special_ot_start')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="special_ot_min_minutes" class="form-label">Special OT Min. Minutes</label>
            <input id="special_ot_min_minutes" name="special_ot_min_minutes" type="number" step="0.0001" min="0" value="{{ old('special_ot_min_minutes', $policy->special_ot_min_minutes) }}" class="form-input" @disabled($disregardExcess)>
            @error('special_ot_min_minutes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    @can('timekeeping-policy.update')
        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button type="submit" class="btn-primary">Save Settings</button>
        </div>
    @endcan
</form>
