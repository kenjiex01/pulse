@php
    use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
    use App\Support\TimekeepingTemplate as TimekeepingTemplateSupport;
@endphp

@include('partials.live-data-table', [
    'url' => route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'templates']),
    'search' => $search,
    'searchPlaceholder' => 'Search template name or content...',
    'searchId' => 'timekeeping-template-search',
    'paginator' => $records,
    'totalLabel' => 'templates',
    'results' => view('timekeeping.templates._results', [
        'records' => $records,
        'templateTypes' => $templateTypes,
        'openEditId' => $openEditId ?? null,
    ])->render(),
])

@can('timekeeping-policy.create')
    @include('partials.modal', [
        'id' => 'timekeeping-template-create',
        'title' => 'Add New Template',
        'description' => 'Create an email notification template for filed forms',
        'panelClass' => 'modal-panel-lg',
        'open' => $openCreate ?? false,
        'body' => view('timekeeping.templates._form', [
            'record' => null,
            'isEdit' => false,
            'formContext' => 'create-timekeeping-template',
            'templateTypes' => $templateTypes,
        ])->render(),
    ])
@endcan
