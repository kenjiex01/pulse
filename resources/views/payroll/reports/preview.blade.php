@extends('layouts.app')

@section('title', ($preview['title'] ?? 'Report Preview').' — '.config('app.name'))

@section('content')
    @include('partials.flash')

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3" data-print-hide>
        <a href="{{ route('payroll.reports.index') }}" class="btn-secondary">Back to Reports</a>
        <button
            type="button"
            class="btn-secondary"
            data-report-print
            data-report-print-source="report-print-document"
        >
            Print
        </button>
    </div>

    <div class="report-print-sheet rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
        @include('payroll.reports._preview', ['preview' => $preview])
    </div>

    @include('payroll.reports._print-document', ['preview' => $preview])
@endsection
