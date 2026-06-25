@extends('layouts.app')

@section('title', $config['name'].' — '.config('app.name'))

@section('content')
    @php
        $openCreate = ($errors->any() && old('form_context') === "create-$lookup") || request()->boolean('create');
    @endphp

    @include('partials.flash')
    @include('partials.page-header', [
        'title' => $config['name'],
        'description' => 'Maintain '.$config['name'].' used in employee forms.',
        'actionModalId' => auth()->user()->can('hr-lookup.create', $lookup) ? "hr-lookup-create-$lookup" : null,
        'actionLabel' => 'Add '.$config['name'],
        'actionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    ])

    @include('partials.live-data-table', [
        'url' => route(\App\Support\HrLookup::routeName($lookup)),
        'search' => $search,
        'searchPlaceholder' => 'Search '.strtolower($config['name']).'...',
        'searchId' => "hr-lookup-search-$lookup",
        'paginator' => $records,
        'totalLabel' => strtolower($config['name']).' records',
        'results' => view('hr-lookups._results', [
            'lookup' => $lookup,
            'config' => $config,
            'records' => $records,
            'selectOptions' => $selectOptions,
            'openEditId' => old('edit_record_id', request('edit')),
        ])->render(),
    ])

    @can('hr-lookup.create', $lookup)
        @include('partials.modal', [
            'id' => "hr-lookup-create-$lookup",
            'title' => 'Add '.$config['name'],
            'description' => 'Create a new '.$config['name'].' record',
            'open' => $openCreate,
            'body' => view('hr-lookups._form', [
                'lookup' => $lookup,
                'config' => $config,
                'record' => null,
                'selectOptions' => $selectOptions,
                'formContext' => "create-$lookup",
            ])->render(),
        ])
    @endcan
@endsection
