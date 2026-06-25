@php
    $primaryKey = $config['primary_key'];
@endphp

<form
    method="POST"
    action="{{ $isEdit
        ? route(\App\Support\TimekeepingPolicy::routeName('equivalents.update'), ['policy' => $policy->timekeeping_policy_id, 'type' => $type, 'record' => $record->{$primaryKey}])
        : route(\App\Support\TimekeepingPolicy::routeName('equivalents.store'), ['policy' => $policy->timekeeping_policy_id, 'type' => $type]) }}"
    class="space-y-4"
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

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="time_from_{{ $formContext }}" class="form-label">From (minutes) <span class="text-red-500">*</span></label>
            <input id="time_from_{{ $formContext }}" name="time_from" type="number" step="0.0001" min="0" value="{{ old('time_from', $record?->time_from) }}" class="form-input" required>
            @error('time_from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="time_to_{{ $formContext }}" class="form-label">To (minutes) <span class="text-red-500">*</span></label>
            <input id="time_to_{{ $formContext }}" name="time_to" type="number" step="0.0001" min="0" value="{{ old('time_to', $record?->time_to) }}" class="form-input" required>
            @error('time_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="equivalent_{{ $formContext }}" class="form-label">Equivalent (minutes) <span class="text-red-500">*</span></label>
            <input id="equivalent_{{ $formContext }}" name="equivalent" type="number" step="0.0001" min="0" value="{{ old('equivalent', $record?->equivalent) }}" class="form-input" required>
            @error('equivalent')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Save Changes' : 'Create' }}</button>
    </div>
</form>
