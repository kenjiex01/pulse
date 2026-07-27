@php
    use App\Models\PayrollCalendar;
    use App\Support\PayrollCalendarModule;
@endphp

@extends('layouts.app')

@section('title', 'Payroll Calendar — '.config('app.name'))

@section('content')
    @php
        $openCreate = ($errors->any() && old('form_context') === "create-{$payTypeSlug}") || ($openCreate ?? false);
    @endphp

    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Payroll Calendar',
        'description' => 'Manage pay periods and loan/deduction schedules.',
        'actionModalId' => auth()->user()->can('payroll-calendar.create') ? "payroll-calendar-create-{$payTypeSlug}" : null,
        'actionLabel' => 'Add Pay Period',
        'actionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    ])

    @include('payroll.calendar._module-tabs-nav', [
        'moduleTab' => $moduleTab,
        'moduleTabs' => $moduleTabs,
        'payTypeSlug' => $payTypeSlug,
        'year' => $year,
    ])

    @include('payroll.calendar._pay-type-tabs-nav')

    @include('payroll.calendar._results')

    @can('payroll-calendar.create')
        @include('partials.modal', [
            'id' => "payroll-calendar-create-{$payTypeSlug}",
            'title' => 'Create Pay Period',
            'description' => PayrollCalendarModule::payTypeLabel($payTypeId).' · '.$year,
            'open' => $openCreate,
            'panelClass' => 'max-w-3xl',
            'body' => view('payroll.calendar._form', [
                'payTypeSlug' => $payTypeSlug,
                'payTypeId' => $payTypeId,
                'year' => $year,
                'period' => null,
                'isEdit' => false,
                'formContext' => "create-{$payTypeSlug}",
                'months' => $months,
                'collegeSelect' => $collegeSelect,
                'userTypeOptions' => $userTypeOptions,
                'nextPayPeriod' => PayrollCalendarModule::nextPayPeriod($payTypeId, $year),
            ])->render(),
        ])
    @endcan
@endsection
