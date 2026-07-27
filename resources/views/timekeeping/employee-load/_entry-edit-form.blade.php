@php
    use App\Support\TimekeepingEmployeeLoad;
    use Carbon\CarbonImmutable;

    /** @var \App\Models\RawEmployeeLoadEntry $entry */
    /** @var callable|null $toTimeInput */
    $toTimeInput = $toTimeInput ?? static function (?string $time): string {
        if ($time === null || $time === '') {
            return '';
        }

        try {
            return CarbonImmutable::parse($time)->format('H:i');
        } catch (\Throwable) {
            return '';
        }
    };

    $timeInValue = old('time_in', ($toTimeInput)($entry->time_in));
    $timeOutValue = old('time_out', ($toTimeInput)($entry->time_out));
    $lateWaivedValue = (bool) old('late_waived', $entry->late_waived);
@endphp

<form
    method="POST"
    action="{{ route(TimekeepingEmployeeLoad::routeName('update-entry'), $entry) }}"
    class="space-y-4"
>
    @csrf
    @method('PUT')

    <input type="hidden" name="edit_employee_load_entry_id" value="{{ $entry->employee_load_entry_id }}">

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
        <p><span class="text-gray-500">Class Schedule:</span> {{ $entry->class_schedule ?: '—' }}</p>
        <p class="mt-1"><span class="text-gray-500">Hours are based on Time In → Time Out.</span> Waive Late restores the late gap so it is not deducted from hours.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="employee-load-entry-time-in-{{ $entry->employee_load_entry_id }}" class="form-label">Time In</label>
            <input
                id="employee-load-entry-time-in-{{ $entry->employee_load_entry_id }}"
                type="time"
                name="time_in"
                class="form-input"
                value="{{ $timeInValue }}"
            >
            @error('time_in')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="employee-load-entry-time-out-{{ $entry->employee_load_entry_id }}" class="form-label">Time Out</label>
            <input
                id="employee-load-entry-time-out-{{ $entry->employee_load_entry_id }}"
                type="time"
                name="time_out"
                class="form-input"
                value="{{ $timeOutValue }}"
            >
            @error('time_out')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800">
        <input
            type="checkbox"
            name="late_waived"
            value="1"
            class="mt-0.5 rounded border-gray-300 text-[#0B318F] focus:ring-[#0B318F]"
            @checked($lateWaivedValue)
        >
        <span>
            <span class="font-medium">Waive Late</span>
            <span class="mt-0.5 block text-xs text-gray-500">
                Do not deduct late from this class’s hours (example: schedule 1:00–2:00, in 1:15 / out 2:00 → 1.00 hr when waived).
            </span>
        </span>
    </label>

    <div>
        <label for="employee-load-entry-remarks-{{ $entry->employee_load_entry_id }}" class="form-label">Remarks</label>
        <input
            id="employee-load-entry-remarks-{{ $entry->employee_load_entry_id }}"
            type="text"
            name="remarks"
            class="form-input"
            value="{{ old('remarks', $entry->remarks) }}"
            maxlength="255"
        >
        @error('remarks')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="employee-load-entry-comments-{{ $entry->employee_load_entry_id }}" class="form-label">Comments</label>
        <input
            id="employee-load-entry-comments-{{ $entry->employee_load_entry_id }}"
            type="text"
            name="comments"
            class="form-input"
            value="{{ old('comments', $entry->comments) }}"
            maxlength="255"
        >
        @error('comments')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    @include('partials.modal-form-actions', ['submitLabel' => 'Save Entry'])
</form>
