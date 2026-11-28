@php
    use App\Models\PayType;

    $fieldIdPrefix = $fieldIdPrefix ?? 'payroll-batch-';
    $yearsByPayType = $yearsByPayType ?? [];
    $periodsByPayType = $periodsByPayType ?? [];
    $postedSourceBatches = $postedSourceBatches ?? ($batchForm['postedSourceBatches'] ?? []);
    $batchNo = old('batch_no', $suggestedBatchNo ?? 1);

    $defaultPayTypeId = (int) old(
        'pay_type_id',
        collect($payTypes)->first(fn ($payType) => ! empty($yearsByPayType[$payType->pay_type_id] ?? []))?->pay_type_id
            ?? $payTypes->first()?->pay_type_id
            ?? 1,
    );
    $payTypeId = (int) old('pay_type_id', $defaultPayTypeId);

    $availableYears = $yearsByPayType[$payTypeId] ?? [];
    $payYear = (int) old('pay_year', $availableYears[0] ?? 0);
    $availablePeriods = $periodsByPayType[$payTypeId][$payYear] ?? [];
    $payrollCalendarId = (int) old('payroll_calendar_id', $availablePeriods[0]['id'] ?? 0);
    $taxComputationId = (int) old('withholding_tax_computation_id', $taxComputations->first()?->withholding_tax_computation_id ?? 1);
    $selectedSourceIds = array_map('intval', (array) old('source_payroll_batch_ids', []));
    $isThirteenthMonth = $payTypeId === PayType::THIRTEENTH_MONTH;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="{{ $fieldIdPrefix }}batch-no" class="form-label">Batch No. <span class="text-red-500">*</span></label>
        <input
            type="number"
            id="{{ $fieldIdPrefix }}batch-no"
            name="batch_no"
            value="{{ $batchNo }}"
            min="1"
            max="99999999"
            class="form-input"
            required
        >
        @error('batch_no')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="{{ $fieldIdPrefix }}pay-type" class="form-label">Pay Type <span class="text-red-500">*</span></label>
        <select
            id="{{ $fieldIdPrefix }}pay-type"
            name="pay_type_id"
            class="form-input"
            data-pb-pay-type
            data-no-searchable-select
            required
        >
            @foreach ($payTypes as $payType)
                <option value="{{ $payType->pay_type_id }}" @selected($payTypeId === (int) $payType->pay_type_id)>
                    {{ $payType->pay_type }}
                </option>
            @endforeach
        </select>
        @error('pay_type_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="{{ $fieldIdPrefix }}pay-year" class="form-label">Pay Year <span class="text-red-500">*</span></label>
        <select
            id="{{ $fieldIdPrefix }}pay-year"
            name="pay_year"
            class="form-input"
            data-pb-pay-year
            data-no-searchable-select
            @disabled($availableYears === [])
            required
        >
            @forelse ($availableYears as $year)
                <option value="{{ $year }}" @selected($payYear === (int) $year)>{{ $year }}</option>
            @empty
                <option value="">No pay year defined</option>
            @endforelse
        </select>
        @error('pay_year')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="{{ $fieldIdPrefix }}pay-period" class="form-label">Pay Period <span class="text-red-500">*</span></label>
        <select
            id="{{ $fieldIdPrefix }}pay-period"
            name="payroll_calendar_id"
            class="form-input"
            data-pb-pay-period
            data-no-searchable-select
            @disabled($availablePeriods === [])
            required
        >
            @forelse ($availablePeriods as $period)
                <option value="{{ $period['id'] }}" @selected($payrollCalendarId === (int) $period['id'])>
                    {{ $period['label'] }}
                </option>
            @empty
                <option value="">No pay period defined</option>
            @endforelse
        </select>
        @error('payroll_calendar_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-4">
    <span class="form-label">Tax Computation <span class="text-red-500">*</span></span>
    <div class="mt-2 flex flex-wrap gap-4">
        @foreach ($taxComputations as $computation)
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="radio"
                    name="withholding_tax_computation_id"
                    value="{{ $computation->withholding_tax_computation_id }}"
                    @checked($taxComputationId === (int) $computation->withholding_tax_computation_id)
                    required
                >
                {{ $computation->withholding_tax_computation }}
            </label>
        @endforeach
    </div>
    @error('withholding_tax_computation_id')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="mt-4 {{ $isThirteenthMonth ? '' : 'hidden' }}" data-pb-thirteenth-sources data-pb-thirteenth-id="{{ PayType::THIRTEENTH_MONTH }}">
    <span class="form-label">Posted batches to include <span class="text-red-500">*</span></span>
    <p class="mt-1 text-xs text-gray-500">Only posted batches are listed. 13th month pay is each employee's gross on the selected batches, divided by 12.</p>
    <div class="mt-2 max-h-52 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-3">
        @forelse ($postedSourceBatches as $sourceBatch)
            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    name="source_payroll_batch_ids[]"
                    value="{{ $sourceBatch['id'] }}"
                    class="mt-0.5 rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                    @checked(in_array((int) $sourceBatch['id'], $selectedSourceIds, true))
                >
                <span>{{ $sourceBatch['label'] }}</span>
            </label>
        @empty
            <p class="text-sm text-gray-500">No posted payroll batches yet.</p>
        @endforelse
    </div>
    @error('source_payroll_batch_ids')
        <p class="form-error">{{ $message }}</p>
    @enderror
    @error('source_payroll_batch_ids.*')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="mt-4">
    <label class="inline-flex items-start gap-2 text-sm text-gray-700">
        <input
            type="checkbox"
            name="include_all_employees"
            value="1"
            class="mt-0.5 rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
            @checked(old('include_all_employees'))
        >
        <span>
            Include all employees
            <span class="block text-xs text-gray-500" data-pb-include-all-hint>
                @if ($isThirteenthMonth)
                    Adds employees who are on the selected posted batches and are not yet in another batch for this pay period.
                @else
                    Adds active employees with the same pay type who are not yet in another batch for this pay period.
                @endif
            </span>
        </span>
    </label>
</div>

<script type="application/json" data-pb-defaults>{{ json_encode([
    'payTypeId' => $payTypeId,
    'payYear' => $payYear,
    'payrollCalendarId' => $payrollCalendarId,
]) }}</script>
