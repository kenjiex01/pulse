<form
    method="POST"
    action="{{ route(\App\Support\TimekeepingPolicy::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'team-settings']) }}"
    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6"
    data-timekeeping-settings="team-settings"
>
    @csrf
    @method('PUT')

    <p class="text-sm text-gray-500">Set a default policy for Team Settings.</p>

    <div>
        <h3 class="text-sm font-semibold text-gray-900">Team Settings</h3>
        <div class="mt-3 max-w-md">
            <label for="timekeeping_policy_team_setting_id" class="form-label">Limit on Views <span class="text-red-500">*</span></label>
            <select id="timekeeping_policy_team_setting_id" name="timekeeping_policy_team_setting_id" class="form-input" required>
                <option value="">Please Select</option>
                @foreach ($selectOptions['team_settings'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('timekeeping_policy_team_setting_id', $policy->timekeeping_policy_team_setting_id) === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('timekeeping_policy_team_setting_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    @can('timekeeping-policy.update')
        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button type="submit" class="btn-primary">Save Settings</button>
        </div>
    @endcan
</form>
