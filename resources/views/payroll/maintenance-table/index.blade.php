@extends('layouts.app')

@section('title', 'Maintenance Table — '.config('app.name'))

@section('content')
    @php
        $openCreate = ($errors->any() && old('form_context') === "create-$tab") || request()->boolean('create');
    @endphp

    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Maintenance Table',
        'description' => 'Maintain payroll income, deduction, loan, and leave types.',
        'actionModalId' => auth()->user()->can('payroll-maintenance.create') ? "payroll-maintenance-create-$tab" : null,
        'actionLabel' => 'Add '.$config['name'],
        'actionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    ])

    <div class="employee-tabs-shell mb-4">
        <nav class="flex flex-wrap gap-1" role="tablist">
            @foreach ($tabs as $tabKey => $tabLabel)
                <a
                    href="{{ route(\App\Support\PayrollMaintenance::routeName('index'), ['tab' => $tabKey, 'search' => $search ?: null]) }}"
                    role="tab"
                    class="employee-tab-btn {{ $tab === $tabKey ? 'employee-tab-btn-active' : '' }}"
                    aria-selected="{{ $tab === $tabKey ? 'true' : 'false' }}"
                >
                    {{ $tabLabel }}
                </a>
            @endforeach
        </nav>
    </div>

    @include('partials.live-data-table', [
        'url' => route(\App\Support\PayrollMaintenance::routeName('index'), ['tab' => $tab]),
        'search' => $search,
        'searchPlaceholder' => 'Search '.strtolower($config['name']).'...',
        'searchId' => "payroll-maintenance-search-$tab",
        'paginator' => $records,
        'totalLabel' => strtolower($config['name']).' records',
        'results' => view('payroll.maintenance-table._results', [
            'tab' => $tab,
            'config' => $config,
            'records' => $records,
            'selectOptions' => $selectOptions,
            'openEditId' => old('edit_record_id', request('edit')),
        ])->render(),
    ])

    @can('payroll-maintenance.create')
        @include('partials.modal', [
            'id' => "payroll-maintenance-create-$tab",
            'title' => 'Add '.$config['name'],
            'description' => 'Create a new '.$config['name'].' record',
            'open' => $openCreate,
            'body' => view('payroll.maintenance-table._form', [
                'tab' => $tab,
                'config' => $config,
                'record' => null,
                'isEdit' => false,
                'formContext' => "create-$tab",
                'selectOptions' => $selectOptions,
            ])->render(),
        ])
    @endcan
@endsection
