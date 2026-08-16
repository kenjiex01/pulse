@php
    $previousSalaries = ($previousSalaries ?? collect())->values();
@endphp

@if ($previousSalaries->isEmpty())
    <p class="text-sm text-gray-500">No previous salary records yet.</p>
@else
    <p class="mb-3 text-sm text-gray-600">Select a previous salary to view its details.</p>

    <div data-client-paginate data-page-size="10" data-paginate-always-show="1">
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-[720px] text-sm">
                <thead>
                    <tr>
                        <th>Effectivity From</th>
                        <th>Effectivity To</th>
                        <th>Pay Type</th>
                        <th class="text-right">Basic Income</th>
                        <th class="text-right">Hourly Rate</th>
                    </tr>
                </thead>
                <tbody data-previous-salary-list>
                    @foreach ($previousSalaries as $previousSalary)
                        @php
                            $previousSalary->loadMissing(['payType', 'incomes.incomeType']);
                            $basicIncome = $previousSalary->basicIncomeAmount();
                            $hourlyRate = $previousSalary->hourlyRate();
                        @endphp
                        <tr
                            class="cursor-pointer transition-colors hover:bg-gray-50"
                            data-paginate-row
                            data-previous-salary-select="{{ $previousSalary->employee_salary_id }}"
                            role="button"
                            tabindex="0"
                        >
                            <td class="font-medium text-gray-900">{{ $previousSalary->date_effective_from?->format('M d, Y') ?: '—' }}</td>
                            <td class="text-gray-600">{{ $previousSalary->date_effective_to?->format('M d, Y') ?: '—' }}</td>
                            <td class="text-gray-600">{{ $previousSalary->payType?->pay_type ?: '—' }}</td>
                            <td class="text-right text-gray-900">{{ number_format($basicIncome, 2) }}</td>
                            <td class="text-right text-gray-900">{{ $hourlyRate !== null ? number_format($hourlyRate, 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @include('partials.client-pagination-controls', ['defaultPageSize' => 10])
    </div>

    <div class="mt-6 space-y-4" data-previous-salary-details>
        @foreach ($previousSalaries as $previousSalary)
            @include('employees.partials._employee-salary-previous-detail', [
                'previousSalary' => $previousSalary,
            ])
        @endforeach
    </div>
@endif
