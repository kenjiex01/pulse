@php
    $toilEnabled = (bool) old('enable_toil', $policy->enable_toil);
@endphp

<form
    method="POST"
    action="{{ route(\App\Support\TimekeepingPolicy::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'toil-settings']) }}"
    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6"
    data-timekeeping-settings="toil-settings"
>
    @csrf
    @method('PUT')

    <p class="text-sm text-gray-500">Set up TOIL.</p>

    <div class="space-y-4">
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="enable_toil" value="0">
            <input type="checkbox" name="enable_toil" value="1" data-toil-toggle @checked($toilEnabled)>
            Enable
        </label>

        <div class="grid gap-4 md:grid-cols-2" data-toil-panel @class(['opacity-50' => ! $toilEnabled])>
            <div>
                <label for="exp_days" class="form-label">Number of days before expiration</label>
                <input id="exp_days" name="exp_days" type="number" min="0" maxlength="4" value="{{ old('exp_days', $policy->exp_days ?? 0) }}" class="form-input" data-toil-field @disabled(! $toilEnabled)>
                @error('exp_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="min_toil_hours" class="form-label">Minimum TOIL hours</label>
                <input id="min_toil_hours" name="min_toil_hours" type="number" step="0.0001" min="0" maxlength="6" value="{{ old('min_toil_hours', $policy->min_toil_hours ?? 0) }}" class="form-input" data-toil-field @disabled(! $toilEnabled)>
                @error('min_toil_hours')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="max_toil_hours" class="form-label">Maximum TOIL hours</label>
                <input id="max_toil_hours" name="max_toil_hours" type="number" step="0.0001" min="0" maxlength="6" value="{{ old('max_toil_hours', $policy->max_toil_hours ?? 0) }}" class="form-input" data-toil-field @disabled(! $toilEnabled)>
                @error('max_toil_hours')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    @can('timekeeping-policy.update')
        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button type="submit" class="btn-primary">Save Settings</button>
        </div>
    @endcan
</form>
