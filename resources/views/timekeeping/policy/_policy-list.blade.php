@php
    $columns = config('timekeeping_policy.list_columns', []);
@endphp

@include('partials.live-data-table', [
    'url' => route(\App\Support\TimekeepingPolicy::routeName('module'), ['tab' => 'policy']),
    'search' => $search,
    'searchPlaceholder' => 'Search policy code, name, or description...',
    'searchId' => 'timekeeping-policy-search',
    'paginator' => $policies,
    'totalLabel' => 'policies',
    'results' => view('timekeeping.policy._policy-results', [
        'policies' => $policies,
        'columns' => $columns,
        'openEditId' => $openEditId ?? null,
    ])->render(),
])

@can('timekeeping-policy.create')
    @include('partials.modal', [
        'id' => 'timekeeping-policy-create',
        'title' => 'New Timekeeping Policy',
        'description' => 'Create a new timekeeping policy',
        'open' => $openCreate ?? false,
        'body' => view('timekeeping.policy._policy-form', [
            'record' => null,
            'isEdit' => false,
            'formContext' => 'create-policy',
        ])->render(),
    ])
@endcan
