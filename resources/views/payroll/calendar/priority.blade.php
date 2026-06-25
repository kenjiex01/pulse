@php
    use App\Support\PayrollCalendarModule;
@endphp

@extends('layouts.app')

@section('title', 'Payroll Calendar — Priority — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Payroll Calendar',
        'description' => 'Manage pay periods, loan/deduction schedules, and deduction priority.',
    ])

    @include('payroll.calendar._module-tabs-nav', [
        'moduleTab' => $moduleTab,
        'moduleTabs' => $moduleTabs,
        'payTypeSlug' => PayrollCalendarModule::defaultPayTypeSlug(),
        'year' => (int) date('Y'),
    ])

    @if (! $settings->is_deduction_loan_priority_enabled)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Deduction &amp; Loan Prioritization is currently <strong>disabled</strong>.
            @can('payroll-calendar.update')
                <form method="POST" action="{{ route(PayrollCalendarModule::routeName('enable-priority')) }}" class="inline">
                    @csrf
                    <button type="submit" class="font-medium text-[#0B318F] underline">Enable this feature</button>
                </form>
            @endcan
        </div>
    @endif

    @include('partials.live-data-table', [
        'url' => route(PayrollCalendarModule::routeName('priority')),
        'search' => $search,
        'searchPlaceholder' => 'Search deduction or loan...',
        'searchId' => 'payroll-calendar-priority-search',
        'paginator' => $priorities,
        'totalLabel' => 'priorities',
        'results' => view('payroll.calendar._priority-results', compact('priorities', 'search', 'settings'))->render(),
    ])
@endsection
