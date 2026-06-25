@php
    $fieldIdPrefix = $fieldIdPrefix ?? 'payroll-batch-';
    $yearsByPayType = $yearsByPayType ?? [];
    $periodsByPayType = $periodsByPayType ?? [];
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
            <span class="block text-xs text-gray-500">Adds active employees with the same pay type who are not yet in another batch for this pay period.</span>
        </span>
    </label>
</div>

<script type="application/json" data-pb-defaults>{{ json_encode([
    'payTypeId' => $payTypeId,
    'payYear' => $payYear,
    'payrollCalendarId' => $payrollCalendarId,
]) }}</script>
