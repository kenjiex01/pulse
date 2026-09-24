@php
    $detailId = (string) $previousEmployment->employment_info_id;
@endphp

<div class="hidden rounded-lg border border-gray-200 bg-white p-4" data-previous-employment-detail="{{ $detailId }}">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div><p class="text-sm text-gray-600">Effectivity From</p><p class="mt-1 text-sm font-medium text-gray-900">{{ $previousEmployment->date_effective_from?->format('M d, Y') ?: '—' }}</p></div>
        <div><p class="text-sm text-gray-600">Effectivity To</p><p class="mt-1 text-sm font-medium text-gray-900">{{ $previousEmployment->date_effective_to?->format('M d, Y') ?: '—' }}</p></div>
        <div><p class="text-sm text-gray-600">Category</p><p class="mt-1 text-sm font-medium text-gray-900">{{ $previousEmployment->user_type_label ?: '—' }}</p></div>
        <div><p class="text-sm text-gray-600">Position</p><p class="mt-1 text-sm font-medium text-gray-900">{{ $previousEmployment->position ?: '—' }}</p></div>
        <div><p class="text-sm text-gray-600">Designation</p><p class="mt-1 text-sm font-medium text-gray-900">{{ $previousEmployment->designation ?: '—' }}</p></div>
        <div><p class="text-sm text-gray-600">Rank</p><p class="mt-1 text-sm font-medium text-gray-900">{{ $previousEmployment->rank ?: '—' }}</p></div>
        <div><p class="text-sm text-gray-600">Employment Type</p><p class="mt-1 text-sm font-medium text-gray-900">{{ $previousEmployment->employment_type ?: '—' }}</p></div>
        <div><p class="text-sm text-gray-600">Hire Date</p><p class="mt-1 text-sm font-medium text-gray-900">{{ $previousEmployment->hire_date?->format('M d, Y') ?: '—' }}</p></div>
        <div><p class="text-sm text-gray-600">Last Payroll Date</p><p class="mt-1 text-sm font-medium text-gray-900">{{ $previousEmployment->last_payroll_date?->format('M d, Y') ?: '—' }}</p></div>
        <div><p class="text-sm text-gray-600">Separation Date</p><p class="mt-1 text-sm font-medium text-gray-900">{{ $previousEmployment->separation_date?->format('M d, Y') ?: '—' }}</p></div>
    </div>
</div>
