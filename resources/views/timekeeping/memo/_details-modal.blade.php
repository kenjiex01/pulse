@php
    $query = request()->only(['date_from', 'date_to', 'violation_type', 'min_count', 'search']);
    $typeLabel = \App\Models\TimekeepingMemoSetup::labelForType($memoFilters['violation_type'] ?? 'late');
@endphp

@include('partials.modal', [
    'id' => 'memo-details-modal',
    'title' => 'Violation Details',
    'description' => $employee->full_name.' · '.$campusLabel.' · '.$typeLabel,
    'open' => true,
    'panelClass' => 'max-w-3xl',
    'body' => view('timekeeping.memo._details-content', [
        'employee' => $employee,
        'memoFilters' => $memoFilters,
        'days' => $days,
        'query' => $query,
    ])->render(),
])
