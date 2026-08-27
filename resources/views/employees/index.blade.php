@extends('layouts.app')

@section('title', 'Employees — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Employee Management',
        'description' => 'Manage employee records with employment status, compliance, and contact information.',
        'tertiaryActionModalId' => auth()->user()->can('syncFromSkolaris', \App\Models\Employee::class) ? 'employee-skolaris-sync-modal' : null,
        'tertiaryActionLabel' => 'Approve',
        'tertiaryActionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>',
        'tertiaryActionBadgeHtml' => '<span class="pointer-events-none absolute -right-1.5 -top-1.5 z-10 hidden h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold leading-none text-white shadow-sm" data-employee-sync-count></span>',
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

    @can('syncFromSkolaris', \App\Models\Employee::class)
        @include('partials.modal', [
            'id' => 'employee-skolaris-sync-modal',
            'title' => 'Approve from ISKOLARIS',
            'description' => 'Review employee profiles that can be pulled into People360.',
            'panelClass' => 'max-w-5xl',
            'body' => view('employees._sync-form')->render(),
        ])
        @include('partials.modal', [
            'id' => 'employee-skolaris-sync-view-modal',
            'title' => 'Profile changes',
            'description' => 'Fields that differ between People360 and ISKOLARIS.',
            'panelClass' => 'max-w-3xl',
            'body' => view('employees._sync-view')->render(),
        ])
    @endcan

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
