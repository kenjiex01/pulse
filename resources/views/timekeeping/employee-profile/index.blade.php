@extends('layouts.app')

@section('title', 'Employee Profile — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Employee Profile',
        'description' => 'Configure timekeeping settings for each employee — holiday group, shift code, policy, and rest days.',
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
@endsection
