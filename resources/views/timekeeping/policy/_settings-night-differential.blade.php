@php
    $computeNightDiff = filter_var(
        old('compute_night_diff', filled($policy->night_diff_start) && filled($policy->night_diff_end)),
        FILTER_VALIDATE_BOOLEAN
    );
    $nightDiffStart = $computeNightDiff ? old('night_diff_start', $policy->night_diff_start) : '';
    $nightDiffEnd = $computeNightDiff ? old('night_diff_end', $policy->night_diff_end) : '';
@endphp

<form
    method="POST"
    action="{{ route(\App\Support\TimekeepingPolicy::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'night-differential']) }}"
    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6"
    data-timekeeping-settings="night-differential"
>
    @csrf
    @method('PUT')

    <fieldset class="rounded-lg border border-gray-200 p-4" data-nd-compute-panel @class(['opacity-50' => ! $computeNightDiff])>
        <legend class="px-1 text-sm font-medium text-gray-900">
            <label class="inline-flex items-center gap-2">
                <input type="hidden" name="compute_night_diff" value="0">
                <input
                    type="checkbox"
                    name="compute_night_diff"
                    value="1"
                    data-nd-compute-toggle
                    @checked($computeNightDiff)
                >
                Compute Night Differential
            </label>
        </legend>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="night_diff_start" class="form-label">Start Time</label>
                <div class="flex items-center gap-2">
                    <input
                        id="night_diff_start"
                        name="night_diff_start"
                        type="text"
                        maxlength="5"
                        placeholder="22:00"
                        value="{{ $nightDiffStart }}"
                        class="form-input max-w-[10rem]"
                        data-nd-time-field
                        @disabled(! $computeNightDiff)
                    >
                    <span class="text-sm text-gray-400">(hh:mm)</span>
                </div>
                @error('night_diff_start')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="night_diff_end" class="form-label">End Time</label>
                <div class="flex items-center gap-2">
                    <input
                        id="night_diff_end"
                        name="night_diff_end"
                        type="text"
                        maxlength="5"
                        placeholder="06:00"
                        value="{{ $nightDiffEnd }}"
                        class="form-input max-w-[10rem]"
                        data-nd-time-field
                        @disabled(! $computeNightDiff)
                    >
                    <span class="text-sm text-gray-400">(hh:mm)</span>
                </div>
                @error('night_diff_end')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    @can('timekeeping-policy.update')
        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button type="submit" class="btn-primary">Save Settings</button>
        </div>
    @endcan
</form>
