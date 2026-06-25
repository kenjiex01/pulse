@extends('layouts.app')

@section('title', 'Rate Definition — '.config('app.name'))

@section('content')
    @php
        $isDayTypes = $tab === 'day-types';
        $openCreate = $isDayTypes && (($errors->any() && old('form_context') === "create-$tab") || request()->boolean('create'));
        $createRoute = match ($tab) {
            'rate-groups' => route('payroll.rate-definitions.rate-groups.create'),
            'nd-rate-groups' => route('payroll.rate-definitions.nd-rate-groups.create'),
            default => null,
        };
    @endphp

    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Rate Definition',
        'description' => 'Configure rate groups, night differential rate groups, and day types.',
        'actionUrl' => ! $isDayTypes && $createRoute && auth()->user()->can('rate-definition.create') ? $createRoute : null,
        'actionModalId' => $isDayTypes && auth()->user()->can('rate-definition.create') ? "rate-definition-create-$tab" : null,
        'actionLabel' => 'Add '.$config['name'],
        'actionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    ])

    <div class="employee-tabs-shell mb-4">
        <nav class="flex flex-wrap gap-1" role="tablist">
            @foreach ($tabs as $tabKey => $tabLabel)
                <a
                    href="{{ route(\App\Support\RateDefinition::routeName('index'), ['tab' => $tabKey, 'search' => $search ?: null]) }}"
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
        'url' => route(\App\Support\RateDefinition::routeName('index'), ['tab' => $tab]),
        'search' => $search,
        'searchPlaceholder' => 'Search '.strtolower($config['name']).'...',
        'searchId' => "rate-definition-search-$tab",
        'paginator' => $records,
        'totalLabel' => strtolower($config['name']).' records',
        'results' => view('payroll.rate-definitions._results', [
            'tab' => $tab,
            'config' => $config,
            'records' => $records,
            'selectOptions' => $selectOptions,
            'openEditId' => old('edit_record_id', request('edit')),
        ])->render(),
    ])

    @if ($isDayTypes)
        @can('rate-definition.create')
            @include('partials.modal', [
                'id' => "rate-definition-create-$tab",
                'title' => 'Add '.$config['name'],
                'description' => 'Create a new '.$config['name'].' record',
                'open' => $openCreate,
                'body' => view('payroll.rate-definitions._form-day-types', [
                    'record' => null,
                    'isEdit' => false,
                    'formContext' => "create-$tab",
                    'selectOptions' => $selectOptions,
                ])->render(),
            ])
        @endcan
    @endif
@endsection
