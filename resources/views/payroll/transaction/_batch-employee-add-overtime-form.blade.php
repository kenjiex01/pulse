@php
    $calendar = $batch->payrollCalendar;
    $minDate = $calendar?->dt_from?->format('Y-m-d');
    $maxDate = $calendar?->dt_to?->format('Y-m-d');
    $previewUrl = route('payroll.transaction.employees.overtime-approvals.preview', [$batch, $detail]);
@endphp

<form
    method="POST"
    action="{{ route('payroll.transaction.employees.overtime-approvals.store', [$batch, $detail]) }}"
    class="space-y-4"
    data-ot-approval-form
    data-ot-preview-url="{{ $previewUrl }}"
>
    @csrf
    <input type="hidden" name="form_context" value="create-payroll-batch-overtime">
    <input type="hidden" name="payroll_batch_detail_id" value="{{ $detail->payroll_batch_detail_id }}">
    <input type="hidden" name="batch_employee_search" value="{{ $batchEmployeeSearch }}">
    <input type="hidden" name="search" value="{{ request('search') }}">

    <div>
        <label for="batch-add-ot-work-date" class="form-label">Work Date <span class="text-red-500">*</span></label>
        <input
            type="date"
            id="batch-add-ot-work-date"
            name="work_date"
            class="form-input"
            value="{{ old('work_date', $minDate) }}"
            data-ot-work-date
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

    <div
        data-ot-excess-hint
        class="rounded-md border border-[#00A3E6]/30 bg-[#00A3E6]/5 px-3 py-2 text-xs text-gray-700 hidden"
        role="status"
    ></div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="batch-add-ot-start" class="form-label">OT Start <span class="text-red-500">*</span></label>
            <input
                type="time"
                id="batch-add-ot-start"
                name="ot_start"
                class="form-input"
                value="{{ old('ot_start') }}"
                data-ot-start
                required
            >
            @error('ot_start')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="batch-add-ot-end" class="form-label">OT End <span class="text-red-500">*</span></label>
            <input
                type="time"
                id="batch-add-ot-end"
                name="ot_end"
                class="form-input"
                value="{{ old('ot_end') }}"
                data-ot-end
                required
            >
            @error('ot_end')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <p class="text-xs text-gray-500">
        Choosing a work date auto-fills OT Start/End from excess hours outside the scheduled shift (before start and/or after end). You can narrow the range. Policy OT settings are ignored for manual filings.
    </p>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Save Overtime',
        'cancelModalId' => 'payroll-batch-add-overtime-modal',
    ])
</form>
