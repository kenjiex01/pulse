@extends('layouts.app')

@section('title', 'Employee Profile — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Employee Profile',
        'description' => 'Configure timekeeping settings for each employee — holiday group, shift code, policy, and rest days.',
        'actionModalId' => auth()->user()?->can('employee-profile.update') ? 'employee-profile-upload-modal' : null,
        'actionLabel' => 'Upload',
    ])

    @include('partials.live-data-table', [
        'url' => route(\App\Support\TimekeepingEmployeeProfile::routeName('index')),
        'search' => $search,
        'searchPlaceholder' => 'Search employee number or name...',
        'searchId' => 'employee-profile-search',
        'paginator' => $employees,
        'totalLabel' => 'employees',
        'results' => view('timekeeping.employee-profile._results', [
            'employees' => $employees,
            'search' => $search,
            'formOptions' => $formOptions,
            'openSetupEmployeeId' => $openSetupEmployeeId ?? null,
            'openViewEmployeeId' => $openViewEmployeeId ?? null,
        ])->render(),
    ])

    @can('employee-profile.update')
        @include('partials.modal', [
            'id' => 'employee-profile-upload-modal',
            'title' => 'Upload Employee Setup',
            'description' => 'Download the pre-filled template, update holiday group, policy, shift code, rest days, and flags, then upload.',
            'open' => $openUpload ?? false,
            'panelClass' => 'max-w-2xl',
            'body' => view('timekeeping.employee-profile._upload-form')->render(),
        ])

        @if ($openPreview ?? false)
            @include('partials.modal', [
                'id' => 'employee-profile-upload-preview-modal',
                'title' => 'Upload Preview',
                'description' => 'Review valid rows and errors before updating employee timekeeping setup.',
                'open' => true,
                'panelClass' => 'max-w-5xl',
                'body' => view('timekeeping.employee-profile._upload-preview', [
                    'staging' => $staging ?? null,
                    'stagingToken' => $stagingToken ?? null,
                ])->render(),
            ])
        @endif
    @endcan
@endsection
