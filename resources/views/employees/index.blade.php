@extends('layouts.app')

@section('title', 'Employees — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Employee Management',
        'description' => 'Manage employee records with employment status, compliance, and contact information.',
        'secondaryActionModalId' => auth()->user()->can('create', \App\Models\Employee::class) ? 'employee-upload-modal' : null,
        'secondaryActionLabel' => 'Upload',
        'secondaryActionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>',
        'actionUrl' => auth()->user()->can('create', \App\Models\Employee::class) ? route('employees.create') : null,
        'actionLabel' => 'Add Employee',
        'actionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    ])

    @include('partials.live-data-table', [
        'url' => route('employees.index'),
        'search' => $search,
        'searchPlaceholder' => 'Name, email, employee #, position...',
        'searchId' => 'employee-search',
        'paginator' => $employees,
        'totalLabel' => 'employees',
        'filters' => view('employees._filters', compact('status', 'compliance'))->render(),
        'results' => view('employees._results', compact('employees', 'stats'))->render(),
    ])

    @can('create', \App\Models\Employee::class)
        @include('partials.modal', [
            'id' => 'employee-upload-modal',
            'title' => 'Upload Employees',
            'description' => 'Import employee master data or update salary records from a template.',
            'open' => $openUpload ?? false,
            'panelClass' => 'max-w-2xl',
            'body' => view('employees._upload-form')->render(),
        ])

        @if ($openPreview ?? false)
            @include('partials.modal', [
                'id' => 'employee-upload-preview-modal',
                'title' => 'Upload Preview',
                'description' => 'Review valid rows and errors before importing.',
                'open' => true,
                'panelClass' => 'max-w-3xl',
                'body' => view('employees._upload-preview', [
                    'staging' => $staging ?? null,
                    'stagingToken' => $stagingToken ?? '',
                ])->render(),
            ])
        @endif
    @endcan
@endsection
