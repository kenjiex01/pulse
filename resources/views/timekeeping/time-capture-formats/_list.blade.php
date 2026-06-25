@php
    use App\Support\TimeCaptureFormat as TimeCaptureFormatSupport;
    use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
@endphp

@include('partials.live-data-table', [
    'url' => route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'time-capturing-settings']),
    'search' => $search,
    'searchPlaceholder' => 'Search device name or description...',
    'searchId' => 'time-capture-format-search',
    'paginator' => $records,
    'totalLabel' => 'time capture formats',
    'results' => view('timekeeping.time-capture-formats._results', [
        'records' => $records,
        'openEditId' => $openEditId ?? null,
    ])->render(),
])

@can('timekeeping-policy.create')
    @include('partials.modal', [
        'id' => 'time-capture-format-create',
        'title' => 'Add Time Capture Format',
        'description' => 'Define device upload format and column mappings',
        'panelClass' => 'modal-panel-lg',
        'open' => $openCreate ?? false,
        'body' => view('timekeeping.time-capture-formats._form', [
            'record' => null,
            'isEdit' => false,
            'formContext' => 'create-time-capture-format',
        ])->render(),
    ])
@endcan
