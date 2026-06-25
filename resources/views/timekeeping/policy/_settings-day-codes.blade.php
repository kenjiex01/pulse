<form
    method="POST"
    action="{{ route(\App\Support\TimekeepingPolicy::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'day-codes']) }}"
    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6"
>
    @csrf
    @method('PUT')

    <p class="text-sm text-gray-500">Set default day codes used in work schedule uploads and processing.</p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            'sunday' => 'Sunday',
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
        ] as $field => $label)
            <div>
                <label for="{{ $field }}" class="form-label">{{ $label }} Code <span class="text-red-500">*</span></label>
                <input
                    id="{{ $field }}"
                    name="{{ $field }}"
                    type="text"
                    maxlength="1"
                    value="{{ old($field, $dayCodes->{$field}) }}"
                    class="form-input uppercase"
                    required
                >
                @error($field)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @endforeach
    </div>

    @error('sunday')
        @if (str_contains($message, 'unique'))
            <p class="text-sm text-red-600">Day codes must be 7 unique single characters.</p>
        @endif
    @enderror

    @can('timekeeping-policy.update')
        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button type="submit" class="btn-primary">Save Day Codes</button>
        </div>
    @endcan
</form>
