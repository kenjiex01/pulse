@php
    $typeLabel = \App\Models\TimekeepingMemoSetup::labelForType($memoFilters['violation_type'] ?? 'late');
@endphp

@include('partials.modal', [
    'id' => 'memo-preview-modal',
    'title' => 'Memo Preview',
    'description' => $employee->full_name.' · '.$campusLabel.' · '.$typeLabel,
    'open' => true,
    'panelClass' => 'max-w-4xl modal-panel-document-preview',
    'bodyClass' => 'modal-body-document-preview',
    'body' => view('timekeeping.memo._preview-content', [
        'employee' => $employee,
        'memoFilters' => $memoFilters,
        'preview' => $preview,
        'campusLabel' => $campusLabel,
    ])->render(),
])
