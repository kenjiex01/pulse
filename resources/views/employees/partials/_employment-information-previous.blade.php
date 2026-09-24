@php
    $previousEmployments = ($previousEmployments ?? collect())->values();
@endphp

@if ($previousEmployments->isEmpty())
    <p class="text-sm text-gray-500">No previous employment records yet.</p>
@else
    <p class="mb-3 text-sm text-gray-600">Select a previous employment record to view its details.</p>

    <div data-client-paginate data-page-size="10" data-paginate-always-show="1">
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-[720px] text-sm">
                <thead>
                    <tr>
                        <th>Effectivity From</th>
                        <th>Effectivity To</th>
                        <th>Position</th>
                        <th>Employment Type</th>
                        <th>Separation Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($previousEmployments as $previousEmployment)
                        <tr
                            class="cursor-pointer transition-colors hover:bg-gray-50"
                            data-paginate-row
                            data-previous-employment-select="{{ $previousEmployment->employment_info_id }}"
                            role="button"
                            tabindex="0"
                        >
                            <td class="font-medium text-gray-900">{{ $previousEmployment->date_effective_from?->format('M d, Y') ?: '—' }}</td>
                            <td class="text-gray-600">{{ $previousEmployment->date_effective_to?->format('M d, Y') ?: '—' }}</td>
                            <td class="text-gray-600">{{ $previousEmployment->position ?: '—' }}</td>
                            <td class="text-gray-600">{{ $previousEmployment->employment_type ?: '—' }}</td>
                            <td class="text-gray-600">{{ $previousEmployment->separation_date?->format('M d, Y') ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @include('partials.client-pagination-controls', ['defaultPageSize' => 10])
    </div>

    <div class="mt-6 space-y-4">
        @foreach ($previousEmployments as $previousEmployment)
            @include('employees.partials._employment-information-previous-detail', [
                'previousEmployment' => $previousEmployment,
            ])
        @endforeach
    </div>
@endif
