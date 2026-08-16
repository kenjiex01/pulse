@php
    $incomeLines = $incomes
        ->map(fn ($income) => [
            'label' => $income->incomeType?->description ?? $income->incomeType?->income_type_code ?? 'Income',
            'hours' => $income->hours !== null ? (float) $income->hours : null,
            'days' => $income->days !== null ? (float) $income->days : null,
            'amount' => (float) $income->taxable + (float) $income->non_taxable,
        ])
        ->filter(fn (array $line) => $line['amount'] !== 0.0)
        ->values();

    $deductionLines = $deductionRows
        ->map(fn (array $deduction) => [
            'label' => $deduction['description'] ?? $deduction['code'] ?? 'Deduction',
            'minutes' => ($deduction['minutes'] ?? null),
            'hours' => ($deduction['show_hours'] ?? false) ? (float) ($deduction['hours'] ?? 0) : null,
            'days' => ($deduction['show_days'] ?? false) ? (float) ($deduction['days'] ?? 0) : null,
            'amount' => (float) $deduction['employee_amount'],
        ])
        ->filter(fn (array $line) => $line['amount'] !== 0.0)
        ->values();
@endphp

<div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
    <div class="grid min-h-[12rem] grid-cols-1 gap-6 p-5 md:grid-cols-2">
        <div class="space-y-1">
            @forelse ($incomeLines as $line)
                <div class="flex items-baseline justify-between gap-4 text-sm">
                    <span class="text-gray-700">{{ $line['label'] }}:</span>
                    <div class="flex shrink-0 items-baseline gap-3 tabular-nums text-gray-900">
                        @if ($line['hours'] !== null)
                            <span class="text-gray-500">{{ number_format($line['hours'], 2) }} hrs</span>
                        @endif
                        @if ($line['days'] !== null)
                            <span class="text-gray-500">{{ number_format($line['days'], 2) }} days</span>
                        @endif
                        <span>{{ number_format($line['amount'], 2) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">No income lines yet.</p>
            @endforelse

            @if ($incomeLines->isNotEmpty() || $grossIncomeTotal > 0)
                <div class="my-3 border-t border-dashed border-gray-300"></div>
                <div class="flex items-baseline justify-between gap-4 text-sm font-semibold text-gray-900">
                    <span>Gross Income:</span>
                    <span class="shrink-0 tabular-nums">{{ number_format($grossIncomeTotal, 2) }}</span>
                </div>
            @endif
        </div>

        <div class="space-y-1 md:ml-auto md:w-full md:max-w-sm">
            @forelse ($deductionLines as $line)
                <div class="flex items-baseline justify-between gap-4 text-sm">
                    <span class="text-gray-700">{{ $line['label'] }}:</span>
                    <div class="flex shrink-0 items-baseline gap-3 tabular-nums text-gray-900">
                        @if ($line['minutes'] !== null)
                            <span class="text-gray-500">{{ number_format((int) $line['minutes']) }} min</span>
                        @endif
                        @if ($line['hours'] !== null)
                            <span class="text-gray-500">{{ number_format($line['hours'], 2) }} hrs</span>
                        @endif
                        @if ($line['days'] !== null)
                            <span class="text-gray-500">{{ number_format($line['days'], 2) }} days</span>
                        @endif
                        <span>-{{ number_format($line['amount'], 2) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">No deduction lines yet.</p>
            @endforelse

            @if ($deductionLines->isNotEmpty() || $employeeShareTotal > 0)
                <div class="my-3 border-t border-dashed border-gray-300"></div>
                <div class="flex items-baseline justify-between gap-4 text-sm font-semibold text-gray-900">
                    <span>Total Deductions:</span>
                    <span class="shrink-0 tabular-nums">-{{ number_format($employeeShareTotal, 2) }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="border-t border-amber-200 bg-amber-50 px-5 py-3">
        <div class="flex items-center justify-end gap-6">
            <span class="text-base font-bold text-red-700">Net Pay:</span>
            <span class="min-w-[7rem] text-right text-base font-bold tabular-nums text-red-700">{{ number_format($netPayTotal, 2) }}</span>
        </div>
    </div>
</div>

@if (! $hasPayrollData)
    <p class="mt-3 text-sm text-gray-500">Process the batch first to compute net pay.</p>
@endif
