@php
    use App\Support\ShiftCode as ShiftCodeSupport;
    use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
@endphp

@include('partials.live-data-table', [
    'url' => route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'shift-codes']),
    'search' => $search,
    'searchPlaceholder' => 'Search shift code, description, or time...',
    'searchId' => 'shift-code-search',
    'paginator' => $records,
    'totalLabel' => 'shift codes',
    'results' => view('timekeeping.shift-codes._results', [
        'records' => $records,
        'openEditId' => $openEditId ?? null,
    ])->render(),
])

@can('timekeeping-policy.create')
    @include('partials.modal', [
        'id' => 'shift-code-create',
        'title' => 'Add New Shift Code',
        'description' => 'Create a new employee shift code',
        'open' => $openCreate ?? false,
        'body' => view('timekeeping.shift-codes._form', [
            'record' => null,
            'isEdit' => false,
            'formContext' => 'create-shift-code',
        ])->render(),
    ])
@endcan
