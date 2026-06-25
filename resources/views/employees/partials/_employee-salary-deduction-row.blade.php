@php
    $salaryIndex = $salaryIndex ?? 0;
    $deductionIndex = $deductionIndex ?? 0;
    $deduction = $deduction ?? [];
@endphp

<tr data-salary-deduction-row>
    <td class="px-3 py-2">
        <input type="checkbox" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" data-salary-deduction-select>
    </td>
    <td class="px-3 py-2">
        <select name="employee_salaries[{{ $salaryIndex }}][deductions][{{ $deductionIndex }}][deduction_type_id]" class="form-input !py-1.5 text-sm" required>
            <option value="">Select Deduction</option>
            @foreach ($formOptions['deductionTypes'] as $deductionType)
                <option value="{{ $deductionType->deduction_type_id }}" @selected((string) old("employee_salaries.$salaryIndex.deductions.$deductionIndex.deduction_type_id", $deduction['deduction_type_id'] ?? '') === (string) $deductionType->deduction_type_id)>
                    {{ $deductionType->description }}
                </option>
            @endforeach
        </select>
    </td>
    <td class="px-3 py-2">
        <input type="number" min="0" step="0.01" name="employee_salaries[{{ $salaryIndex }}][deductions][{{ $deductionIndex }}][employee_amount]" value="{{ old("employee_salaries.$salaryIndex.deductions.$deductionIndex.employee_amount", $deduction['employee_amount'] ?? '0.00') }}" class="form-input !py-1.5 text-sm text-right">
    </td>
    <td class="px-3 py-2">
        <input type="number" min="0" step="0.01" name="employee_salaries[{{ $salaryIndex }}][deductions][{{ $deductionIndex }}][employer_amount]" value="{{ old("employee_salaries.$salaryIndex.deductions.$deductionIndex.employer_amount", $deduction['employer_amount'] ?? '0.00') }}" class="form-input !py-1.5 text-sm text-right">
    </td>
</tr>
