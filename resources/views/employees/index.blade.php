@extends('layouts.app')

@section('title', 'Employees — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Employee Management',
        'description' => 'Manage employee records with employment status, compliance, and contact information.',
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
@endsection
