@php
    $calendar = $batch->payrollCalendar;
    $minDate = $calendar?->dt_from?->format('Y-m-d');
    $maxDate = $calendar?->dt_to?->format('Y-m-d');
@endphp

<form
    method="POST"
    action="{{ route('payroll.transaction.employees.shift-overrides.store', [$batch, $detail]) }}"
    class="space-y-4"
>
    @csrf
    <input type="hidden" name="form_context" value="create-payroll-batch-shift-override">
    <input type="hidden" name="payroll_batch_detail_id" value="{{ $detail->payroll_batch_detail_id }}">
    <input type="hidden" name="batch_employee_search" value="{{ $batchEmployeeSearch }}">
    <input type="hidden" name="search" value="{{ request('search') }}">

    <div>
        <label for="batch-add-shift-work-date" class="form-label">Work Date <span class="text-red-500">*</span></label>
        <input
            type="date"
            id="batch-add-shift-work-date"
            name="work_date"
            class="form-input"
            value="{{ old('work_date', $minDate) }}"
            @if ($minDate) min="{{ $minDate }}" @endif
            @if ($maxDate) max="{{ $maxDate }}" @endif
            required
        >
        @error('work_date')
            <p class="form-error">{{ $message }}</p>
        @enderror
        @if ($calendar)
            <p class="mt-1 text-xs text-gray-500">
                Pay period: {{ $calendar->dt_from->format('M j, Y') }} – {{ $calendar->dt_to->format('M j, Y') }}
            </p>
        @endif
    </div>

    <div>
        <label for="batch-add-shift-code-id" class="form-label">Shift Code <span class="text-red-500">*</span></label>
        <select id="batch-add-shift-code-id" name="shift_code_id" class="form-input" required>
            <option value="">Select shift code</option>
            @foreach ($shiftCodes as $shiftCode)
                <option
                    value="{{ $shiftCode->shift_code_id }}"
                    @selected((string) old('shift_code_id') === (string) $shiftCode->shift_code_id)
                >
                    {{ $shiftCode->shift_code }}
                    @if ($shiftCode->description)
                        — {{ $shiftCode->description }}
                    @endif
                    @if ($shiftCode->time_in && $shiftCode->time_out)
                        ({{ \Illuminate\Support\Str::of($shiftCode->time_in)->substr(0, 5) }}–{{ \Illuminate\Support\Str::of($shiftCode->time_out)->substr(0, 5) }})
                    @endif
                </option>
            @endforeach
        </select>
        @error('shift_code_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Save Shift Code',
        'cancelModalId' => 'payroll-batch-add-shift-code-modal',
    ])
</form>
