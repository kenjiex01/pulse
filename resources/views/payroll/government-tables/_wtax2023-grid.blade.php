@php
    $frequencyLabel = \App\Support\GovernmentTables::WTAX2023_FREQUENCIES[$frequency]['label'] ?? ucfirst($frequency);
@endphp

<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    @can('government-tables.update')
        <form method="POST" action="{{ route('payroll.government-tables.wtax2023.update', ['frequency' => $frequency]) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-700"></th>
                            @for ($column = 1; $column <= \App\Models\GovtTableWtax2023::COLUMN_COUNT; $column++)
                                <th class="px-3 py-2 text-center font-medium text-gray-700">{{ $column }}</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700">Tax Amount</td>
                            @for ($column = 1; $column <= \App\Models\GovtTableWtax2023::COLUMN_COUNT; $column++)
                                <td class="px-3 py-2">
                                    <input type="text" name="columns[{{ $column }}][tax_amount]" value="{{ old("columns.$column.tax_amount", $wtaxGrid[$column]['tax_amount'] ?? '') }}" class="form-input text-right text-sm">
                                </td>
                            @endfor
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700">Plus (%)</td>
                            @for ($column = 1; $column <= \App\Models\GovtTableWtax2023::COLUMN_COUNT; $column++)
                                <td class="px-3 py-2">
                                    <input type="text" name="columns[{{ $column }}][tax_plus]" value="{{ old("columns.$column.tax_plus", $wtaxGrid[$column]['tax_plus'] ?? '') }}" class="form-input text-right text-sm">
                                </td>
                            @endfor
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700 text-center" colspan="{{ \App\Models\GovtTableWtax2023::COLUMN_COUNT + 1 }}">over</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700"></td>
                            @for ($column = 1; $column <= \App\Models\GovtTableWtax2023::COLUMN_COUNT; $column++)
                                <td class="px-3 py-2">
                                    <input type="text" name="columns[{{ $column }}][amount]" value="{{ old("columns.$column.amount", $wtaxGrid[$column]['amount'] ?? '') }}" class="form-input text-right text-sm">
                                </td>
                            @endfor
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary">Save {{ $frequencyLabel }} Tax Table</button>
            </div>
        </form>
    @else
        <p class="text-sm text-gray-500">You do not have permission to update this tax table.</p>
    @endcan
</div>
