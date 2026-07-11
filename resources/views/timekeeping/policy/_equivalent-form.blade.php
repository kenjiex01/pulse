@php
    $primaryKey = $config['primary_key'];
    $supportsMarksAbsent = (bool) ($config['supports_marks_absent'] ?? false);
    $marksAbsentChecked = $supportsMarksAbsent
        && filter_var(old('marks_absent', $record?->marks_absent ?? false), FILTER_VALIDATE_BOOLEAN);
@endphp

<form
    method="POST"
    action="{{ $isEdit
        ? route(\App\Support\TimekeepingPolicy::routeName('equivalents.update'), ['policy' => $policy->timekeeping_policy_id, 'type' => $type, 'record' => $record->{$primaryKey}])
        : route(\App\Support\TimekeepingPolicy::routeName('equivalents.store'), ['policy' => $policy->timekeeping_policy_id, 'type' => $type]) }}"
    class="space-y-4"
    @if ($supportsMarksAbsent) data-equivalent-form-tardiness @endif
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <input type="hidden" name="form_context" value="{{ $formContext }}">
    @if ($isEdit)
        <input type="hidden" name="edit_record_id" value="{{ $record->{$primaryKey} }}">
    @endif

    @if ($config['requires_leave_type'] ?? false)
        <div>
            <label for="leave_type_id_{{ $formContext }}" class="form-label">Leave Type <span class="text-red-500">*</span></label>
            <select id="leave_type_id_{{ $formContext }}" name="leave_type_id" class="form-input" required>
                @foreach ($availableLeaveTypes as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('leave_type_id', $leaveTypeId ?? $record?->leave_type_id) === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('leave_type_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="flex min-w-0 flex-col">
            <label for="time_from_{{ $formContext }}" class="form-label !mb-2 flex min-h-[2.5rem] items-end leading-snug">
                From <span class="text-gray-500">(min)</span> <span class="text-red-500">*</span>
            </label>
            <input id="time_from_{{ $formContext }}" name="time_from" type="number" step="0.0001" min="0" value="{{ old('time_from', $record?->time_from) }}" class="form-input w-full" required>
            @error('time_from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex min-w-0 flex-col">
            <label for="time_to_{{ $formContext }}" class="form-label !mb-2 flex min-h-[2.5rem] items-end leading-snug">
                To <span class="text-gray-500">(min)</span> <span class="text-red-500">*</span>
            </label>
            <input id="time_to_{{ $formContext }}" name="time_to" type="number" step="0.0001" min="0" value="{{ old('time_to', $record?->time_to) }}" class="form-input w-full" required>
            @error('time_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex min-w-0 flex-col">
            <label for="equivalent_{{ $formContext }}" class="form-label !mb-2 flex min-h-[2.5rem] items-end leading-snug">
                Equivalent <span class="text-gray-500">(min)</span> <span class="text-red-500">*</span>
            </label>
            <input
                id="equivalent_{{ $formContext }}"
                name="equivalent"
                type="number"
                step="0.0001"
                min="0"
                value="{{ old('equivalent', $record?->equivalent) }}"
                class="form-input w-full"
                required
                data-equivalent-field
                @readonly($marksAbsentChecked)
            >
            @error('equivalent')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    @if ($supportsMarksAbsent)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
            <label class="flex cursor-pointer items-start gap-3 text-sm text-gray-800">
                <input type="hidden" name="marks_absent" value="0">
                <input
                    type="checkbox"
                    name="marks_absent"
                    value="1"
                    class="mt-0.5"
                    data-marks-absent-toggle
                    @checked($marksAbsentChecked)
                >
                <span>
                    <span class="font-medium text-gray-900">Mark as absent</span>
                    <span class="mt-0.5 block text-xs text-gray-600">
                        For this range (e.g. 16 min and up), the session is absent — not a minute equivalent.
                    </span>
                </span>
            </label>
            @error('marks_absent')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    @endif

    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Save Changes' : 'Create' }}</button>
    </div>
</form>
