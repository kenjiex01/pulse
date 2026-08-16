@include('payroll.reports.options._batch-employee-picker', [
    'pickerPrefix' => 'bir-1601c',
    'title' => 'BIR Form 1601-C Options',
    'help' => 'Monthly Remittance Return of Income Taxes Withheld on Compensation. Select one or more <strong>posted</strong> batches (same pay month/year) and employees. Amounts are summed across selected batches. Preview/PDF uses the official BIR blank form.',
    'report' => $report,
    'postedBatches' => $postedBatches ?? collect(),
])

<div class="mt-4 rounded-lg border border-gray-200 bg-white px-3 py-3">
    <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-800">
        <input
            type="checkbox"
            name="include_annual_13th_month"
            value="1"
            class="mt-0.5 rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
            @checked((bool) old('include_annual_13th_month'))
        >
        <span>
            <span class="font-medium">Include 13th month pay (whole year)</span>
            <span class="mt-0.5 block text-xs font-normal text-gray-500">
                Uses the selected batches’ payroll year. Computes 13th month from posted basic pay (basic ÷ 12) and places it on item 17.
                Leave unchecked to set item 17 to 0.00.
            </span>
        </span>
    </label>
</div>
