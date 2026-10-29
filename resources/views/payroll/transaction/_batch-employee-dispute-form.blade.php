@php
    use App\Models\PayrollDispute;

    $isEdit = $dispute !== null;
    $prefix = $isEdit ? 'dispute-edit-'.$dispute->payroll_dispute_id : 'dispute-add';
    $value = function (string $field) use ($useOld, $dispute) {
        $current = match ($field) {
            'work_date' => $dispute?->work_date?->format('Y-m-d'),
            default => $dispute?->{$field},
        };

        return $useOld ? old($field, $current) : $current;
    };
    $showErrors = $useOld;
    $selectedCategory = (string) ($value('category') ?? '');
    $expected = $isEdit ? number_format($dispute->expectedTotal(), 2, '.', '') : '';
@endphp

<form
    method="POST"
    action="{{ $isEdit
        ? route('payroll.transaction.employees.disputes.update', [$batch, $detail, $dispute])
        : route('payroll.transaction.employees.disputes.store', [$batch, $detail]) }}"
    class="space-y-4"
    data-dispute-form
    data-dispute-prefill-url="{{ route('payroll.transaction.employees.disputes.prefill', [$batch, $detail]) }}"
    data-dispute-preview-url="{{ route('payroll.transaction.employees.disputes.preview', [$batch, $detail]) }}"
    data-dispute-id="{{ $dispute?->payroll_dispute_id }}"
    data-dispute-autofill="{{ $isEdit || $useOld ? '0' : '1' }}"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="edit_payroll_dispute_id" value="{{ $dispute->payroll_dispute_id }}">
    @else
        <input type="hidden" name="form_context" value="create-payroll-dispute">
    @endif
    <input type="hidden" name="payroll_batch_detail_id" value="{{ $detail->payroll_batch_detail_id }}">
    <input type="hidden" name="batch_employee_search" value="{{ $batchEmployeeSearch }}">
    <input type="hidden" name="search" value="{{ request('search') }}">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $prefix }}-work-date" class="form-label">Work Date <span class="text-red-500">*</span></label>
            <input
                type="date"
                id="{{ $prefix }}-work-date"
                name="work_date"
                class="form-input"
                value="{{ $value('work_date') }}"
                data-dispute-work-date
                @if ($maxWorkDate) max="{{ $maxWorkDate }}" @endif
                required
            >
            @if ($showErrors)
                @error('work_date')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            @endif
            <p class="mt-1 text-xs text-gray-500">A day from a previous, already posted pay period.</p>
        </div>
        <div>
            <label for="{{ $prefix }}-category" class="form-label">Dispute Type <span class="text-red-500">*</span></label>
            <select id="{{ $prefix }}-category" name="category" class="form-input" data-dispute-category required>
                <option value="">Select type…</option>
                @foreach (PayrollDispute::CATEGORIES as $code => $label)
                    <option value="{{ $code }}" @selected($selectedCategory === $code)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($showErrors)
                @error('category')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            @endif
        </div>
    </div>

    <div
        data-dispute-already-filed
        class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 hidden"
        role="alert"
    ></div>

    <div
        data-dispute-paid
        class="rounded-md border border-[#00A3E6]/30 bg-[#00A3E6]/5 px-3 py-2 text-xs text-gray-700 hidden"
        role="status"
    ></div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2" data-dispute-field="UPD ODU OPD">
            <label for="{{ $prefix }}-shift" class="form-label">Shift</label>
            <select id="{{ $prefix }}-shift" name="shift_code_id" class="form-input" data-dispute-input="shift_code_id">
                <option value="">Employee's shift on that day</option>
                @foreach ($shiftCodes as $shiftCode)
                    <option value="{{ $shiftCode->shift_code_id }}" @selected((string) $value('shift_code_id') === (string) $shiftCode->shift_code_id)>
                        {{ $shiftCode->shift_code }}
                        @if ($shiftCode->time_in && $shiftCode->time_out)
                            ({{ substr($shiftCode->time_in, 0, 5) }}–{{ substr($shiftCode->time_out, 0, 5) }})
                        @endif
                    </option>
                @endforeach
            </select>
        </div>
        <div data-dispute-field="UPD ODU ODL OPD">
            <label for="{{ $prefix }}-time-in" class="form-label" data-dispute-time-in-label>Time In</label>
            <input type="time" id="{{ $prefix }}-time-in" name="time_in" class="form-input" value="{{ $value('time_in') }}" data-dispute-input="time_in">
            @if ($showErrors)
                @error('time_in')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            @endif
        </div>
        <div data-dispute-field="UPD ODU OPD">
            <label for="{{ $prefix }}-time-out" class="form-label">Time Out</label>
            <input type="time" id="{{ $prefix }}-time-out" name="time_out" class="form-input" value="{{ $value('time_out') }}" data-dispute-input="time_out">
            @if ($showErrors)
                @error('time_out')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            @endif
        </div>
        <div data-dispute-field="UOT OPO">
            <label for="{{ $prefix }}-ot-start" class="form-label">OT Start</label>
            <input type="time" id="{{ $prefix }}-ot-start" name="ot_start" class="form-input" value="{{ $value('ot_start') }}" data-dispute-input="ot_start">
            @if ($showErrors)
                @error('ot_start')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            @endif
        </div>
        <div data-dispute-field="UOT OPO">
            <label for="{{ $prefix }}-ot-end" class="form-label">OT End</label>
            <input type="time" id="{{ $prefix }}-ot-end" name="ot_end" class="form-input" value="{{ $value('ot_end') }}" data-dispute-input="ot_end">
            @if ($showErrors)
                @error('ot_end')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            @endif
        </div>
        <p class="sm:col-span-2 text-xs text-gray-500" data-dispute-field="OPD">Enter the employee's actual Time In / Time Out for that day. Leave both blank if the employee was absent — the whole paid basic for that day is deducted.</p>
        <p class="sm:col-span-2 text-xs text-gray-500" data-dispute-field="OPO">Enter the overtime actually rendered. Leave both blank if no overtime was rendered — the whole paid OT for that day is deducted.</p>
    </div>

    <div>
        <label for="{{ $prefix }}-remarks" class="form-label">Remarks</label>
        <input type="text" id="{{ $prefix }}-remarks" name="remarks" maxlength="255" class="form-input" value="{{ $value('remarks') }}">
        @if ($showErrors)
            @error('remarks')
                <p class="form-error">{{ $message }}</p>
            @enderror
        @endif
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-3">
        <label for="{{ $prefix }}-expected-pay" class="form-label" data-dispute-expected-label>{{ PayrollDispute::isOverPaymentCategory($selectedCategory) ? 'Amount to Deduct' : 'Expected Pay' }}</label>
        <input
            type="text"
            id="{{ $prefix }}-expected-pay"
            class="form-input bg-white font-semibold"
            value="{{ $expected }}"
            placeholder="0.00"
            readonly
            tabindex="-1"
            data-dispute-expected
        >
        <p class="mt-1 text-xs text-gray-600" data-dispute-breakdown></p>
        <ul class="mt-1 space-y-0.5 text-xs text-amber-700" data-dispute-warnings></ul>
        <p class="mt-1 text-xs text-gray-500" data-dispute-expected-help>{{ PayrollDispute::isOverPaymentCategory($selectedCategory)
            ? 'Overpaid amount deducted from the employee in this batch. Final amount is computed when the batch is processed.'
            : 'Amount the employee is expected to receive for this dispute. Final amount is computed when the batch is processed.' }}</p>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => $isEdit ? 'Update Dispute' : 'Save Dispute',
        'cancelModalId' => $modalId,
    ])
</form>
