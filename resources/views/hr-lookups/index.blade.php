@extends('layouts.app')

@section('title', $config['name'].' — '.config('app.name'))

@section('content')
    @php
        $openCreate = ($errors->any() && old('form_context') === "create-$lookup") || request()->boolean('create');
        $openCreateFrequency = ($errors->any() && old('form_context') === 'create-offense-frequencies') || request()->boolean('create_frequency');
        $isOffenseCategories = $lookup === 'offense-categories';
        $supportsUpload = \App\Support\HrLookup::supportsUpload($lookup);
        $canCreate = auth()->user()->can('hr-lookup.create', $lookup);
        $addIcon = '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>';
        $uploadIcon = '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5-5 5 5M12 5v10"/></svg>';
        $openUpload = ($openUpload ?? false) || ($errors->any() && old('form_context') === "upload-$lookup");
    @endphp

    @include('partials.flash')

    @include('partials.page-header', [
        'title' => $config['name'],
        'description' => $config['description'] ?? 'Maintain '.$config['name'].' used in employee forms.',
        'secondaryActionModalId' => $isOffenseCategories && auth()->user()->can('hr-lookup.create', 'offense-frequencies')
            ? 'hr-lookup-create-offense-frequencies'
            : ($supportsUpload && $canCreate ? "hr-lookup-upload-$lookup" : null),
        'secondaryActionLabel' => $isOffenseCategories ? 'Add Frequency' : 'Upload',
        'secondaryActionIcon' => $isOffenseCategories ? $addIcon : $uploadIcon,
        'actionModalId' => $isOffenseCategories
            ? (auth()->user()->can('hr-lookup.create', $lookup) ? "hr-lookup-create-$lookup" : null)
            : (auth()->user()->can('hr-lookup.create', $lookup) ? "hr-lookup-create-$lookup" : null),
        'actionLabel' => $isOffenseCategories ? 'Add Category' : 'Add '.$config['name'],
        'actionIcon' => $addIcon,
    ])

    @if ($isOffenseCategories)
        @include('hr-lookups.offense-categories._manage', [
            'lookup' => $lookup,
            'config' => $config,
            'frequencyConfig' => $frequencyConfig,
            'categories' => $categories,
            'frequencies' => $frequencies,
            'penaltyMatrix' => $penaltyMatrix ?? [],
            'selectOptions' => $selectOptions,
            'openEditId' => old('form_context', '') !== '' && str_starts_with((string) old('form_context'), 'edit-offense-categories-')
                ? old('edit_record_id', request('edit'))
                : request('edit'),
            'openEditFrequencyId' => old('form_context', '') !== '' && str_starts_with((string) old('form_context'), 'edit-offense-frequencies-')
                ? old('edit_record_id', request('edit_frequency'))
                : request('edit_frequency'),
        ])
    @else
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
    @endif

    @if ($supportsUpload && $canCreate)
        @include('partials.modal', [
            'id' => "hr-lookup-upload-$lookup",
            'title' => 'Upload '.$config['name'],
            'description' => 'Download the template, fill it in, then upload it.',
            'open' => $openUpload,
            'panelClass' => 'max-w-2xl',
            'body' => view('hr-lookups._upload-form', [
                'lookup' => $lookup,
                'config' => $config,
            ])->render(),
        ])
    @endif

    @can('hr-lookup.create', $lookup)
        @if ($isOffenseCategories)
            @include('partials.modal', [
                'id' => "hr-lookup-create-$lookup",
                'title' => 'Add Offense Category',
                'description' => 'Create a penalty tier column for the matrix',
                'open' => $openCreate,
                'body' => view('hr-lookups.offense-categories._form', [
                    'lookup' => $lookup,
                    'config' => $config,
                    'record' => null,
                    'frequencies' => $frequencies,
                    'selectOptions' => $selectOptions,
                    'formContext' => "create-$lookup",
                ])->render(),
            ])
        @elseif (! $isOffenseCategories)
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
        @endif
    @endcan

    @if ($isOffenseCategories)
        @can('hr-lookup.create', 'offense-frequencies')
            @include('partials.modal', [
                'id' => 'hr-lookup-create-offense-frequencies',
                'title' => 'Add Offense Frequency',
                'description' => 'Add a new row to the Table of Penalties matrix',
                'open' => $openCreateFrequency,
                'body' => view('hr-lookups.offense-categories._frequency-form', [
                    'lookup' => 'offense-frequencies',
                    'config' => $frequencyConfig,
                    'record' => null,
                    'formContext' => 'create-offense-frequencies',
                ])->render(),
            ])
        @endcan
    @endif
@endsection
