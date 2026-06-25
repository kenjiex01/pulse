@php
    $salaryIndex = $salaryIndex ?? 0;
    $incomeIndex = $incomeIndex ?? 0;
    $income = $income ?? [];
@endphp

<tr data-salary-income-row>
    <td class="px-3 py-2">
        <input type="checkbox" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" data-salary-income-select>
    </td>
    <td class="px-3 py-2">
        <select name="employee_salaries[{{ $salaryIndex }}][incomes][{{ $incomeIndex }}][income_type_id]" class="form-input !py-1.5 text-sm" data-no-searchable-select required>
            <option value="">Select Income</option>
            @foreach ($formOptions['incomeTypes'] as $incomeType)
                <option value="{{ $incomeType->income_type_id }}" @selected((string) old("employee_salaries.$salaryIndex.incomes.$incomeIndex.income_type_id", $income['income_type_id'] ?? '') === (string) $incomeType->income_type_id)>
                    {{ $incomeType->description }}
                </option>
            @endforeach
        </select>
    </td>
    <td class="px-3 py-2">
        <input type="number" min="0" step="0.01" name="employee_salaries[{{ $salaryIndex }}][incomes][{{ $incomeIndex }}][taxable]" value="{{ old("employee_salaries.$salaryIndex.incomes.$incomeIndex.taxable", $income['taxable'] ?? '') }}" class="form-input !py-1.5 text-sm text-right" placeholder="0.00">
    </td>
    <td class="px-3 py-2">
        <input type="number" min="0" step="0.01" name="employee_salaries[{{ $salaryIndex }}][incomes][{{ $incomeIndex }}][non_taxable]" value="{{ old("employee_salaries.$salaryIndex.incomes.$incomeIndex.non_taxable", $income['non_taxable'] ?? '') }}" class="form-input !py-1.5 text-sm text-right" placeholder="0.00">
    </td>
</tr>
