@extends('layouts.app')

@section('title', 'Government Tables — '.config('app.name'))

@section('content')
    @php
        $isWtax2023 = $tab === 'withholding-tax-2023';
        $isAnnual = $isWtax2023 && ($frequency ?? 'daily') === 'annual';
        $allowCreate = ($config['allow_create'] ?? true) && ! $isWtax2023;
        $openCreate = $allowCreate && (($errors->any() && old('form_context') === "create-$tab") || request()->boolean('create'));
        $openAnnualCreate = $isAnnual && (($errors->any() && old('form_context') === 'create-wtax-annual') || request()->boolean('create'));
    @endphp

    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Government Tables',
        'description' => 'Maintain Pag-IBIG, PhilHealth, SSS, and withholding tax tables.',
        'actionModalId' => $allowCreate && auth()->user()->can('government-tables.create') ? "government-tables-create-$tab" : ($isAnnual && auth()->user()->can('government-tables.create') ? 'government-tables-create-wtax-annual' : null),
        'actionLabel' => $isAnnual ? 'Add Annual Range' : 'Add '.$config['name'],
        'actionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    ])

    <div class="employee-tabs-shell mb-4">
        <nav class="flex flex-wrap gap-1" role="tablist">
            @foreach ($tabs as $tabKey => $tabLabel)
                <a
                    href="{{ route(\App\Support\GovernmentTables::routeName('index'), ['tab' => $tabKey, 'search' => $search ?: null]) }}"
                    role="tab"
                    class="employee-tab-btn {{ $tab === $tabKey ? 'employee-tab-btn-active' : '' }}"
                    aria-selected="{{ $tab === $tabKey ? 'true' : 'false' }}"
                >
                    {{ $tabLabel }}
                </a>
            @endforeach
        </nav>
    </div>

    @if ($isWtax2023)
        <div class="employee-tabs-shell mb-4">
            <nav class="flex flex-wrap gap-1" role="tablist">
                @foreach ($wtaxFrequencies as $frequencyKey => $frequencyConfig)
                    <a
                        href="{{ route(\App\Support\GovernmentTables::routeName('index'), ['tab' => $tab, 'frequency' => $frequencyKey, 'search' => $search ?: null]) }}"
                        role="tab"
                        class="employee-tab-btn {{ ($frequency ?? 'daily') === $frequencyKey ? 'employee-tab-btn-active' : '' }}"
                        aria-selected="{{ ($frequency ?? 'daily') === $frequencyKey ? 'true' : 'false' }}"
                    >
                        {{ $frequencyConfig['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>

        @if ($isAnnual)
            @include('partials.live-data-table', [
                'url' => route(\App\Support\GovernmentTables::routeName('index'), ['tab' => $tab, 'frequency' => 'annual']),
                'search' => $search,
                'searchPlaceholder' => 'Search annual tax ranges...',
                'searchId' => 'government-tables-wtax-annual-search',
                'paginator' => $records,
                'totalLabel' => 'annual tax ranges',
                'results' => view('payroll.government-tables._wtax2023-annual-results', [
                    'tab' => $tab,
                    'frequency' => $frequency,
                    'records' => $records,
                    'openEditId' => old('edit_record_id', request('edit')),
                ])->render(),
            ])

            @can('government-tables.create')
                @include('partials.modal', [
                    'id' => 'government-tables-create-wtax-annual',
                    'title' => 'Add Annual Tax Range',
                    'description' => 'Create a new annual withholding tax range',
                    'open' => $openAnnualCreate,
                    'body' => view('payroll.government-tables._form-wtax-annual', [
                        'record' => null,
                        'isEdit' => false,
                        'formContext' => 'create-wtax-annual',
                    ])->render(),
                ])
            @endcan
        @else
            @include('payroll.government-tables._wtax2023-grid', [
                'frequency' => $frequency,
                'wtaxGrid' => $wtaxGrid,
                'wtaxTypeId' => $wtaxTypeId,
            ])
        @endif
    @else
        @include('partials.live-data-table', [
            'url' => route(\App\Support\GovernmentTables::routeName('index'), ['tab' => $tab]),
            'search' => $search,
            'searchPlaceholder' => 'Search '.strtolower($config['name']).'...',
            'searchId' => "government-tables-search-$tab",
            'paginator' => $records,
            'totalLabel' => strtolower($config['name']).' records',
            'results' => view('payroll.government-tables._results', [
                'tab' => $tab,
                'config' => $config,
                'records' => $records,
                'openEditId' => old('edit_record_id', request('edit')),
            ])->render(),
        ])

        @can('government-tables.create')
            @if ($allowCreate)
            @include('partials.modal', [
                'id' => "government-tables-create-$tab",
                'title' => 'Add '.$config['name'],
                'description' => 'Create a new '.$config['name'].' record',
                'open' => $openCreate,
                'body' => view('payroll.government-tables._form', [
                    'tab' => $tab,
                    'config' => $config,
                    'record' => null,
                    'isEdit' => false,
                    'formContext' => "create-$tab",
                ])->render(),
            ])
            @endif
        @endcan
    @endif
@endsection
