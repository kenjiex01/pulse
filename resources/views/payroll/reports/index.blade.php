@extends('layouts.app')

@section('title', 'Reports — '.config('app.name'))

@section('content')
    @php
        use App\Support\PayrollReportsModule;
    @endphp
    @include('partials.flash')

    @include('partials.page-header', [
        'title' => 'Reports',
        'description' => 'Generate payroll and other module reports from saved report definitions.',
    ])

    <div
        class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8"
        data-payroll-reports-root
        @if ($selectedReport && ($lazyLoadReportOptions ?? true))
            data-initial-report-id="{{ $selectedReport->report_id }}"
            data-initial-classification="{{ $classification->code }}"
        @endif
    >
        <form
            method="POST"
            action="{{ route('payroll.reports.generate') }}"
            class="space-y-6"
            data-payroll-reports-form
            data-no-loader
        >
            @csrf

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="report-classification" class="form-label">Report Classification</label>
                    <select
                        id="report-classification"
                        name="classification"
                        class="form-input"
                        data-payroll-report-classification
                    >
                        @foreach ($classifications as $code => $label)
                            <option value="{{ $code }}" @selected($classification->code === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="report-id" class="form-label">Report Type</label>
                    <select
                        id="report-id"
                        name="report_id"
                        class="form-input"
                        required
                        data-payroll-report-select
                        data-options-url="{{ url('payroll/reports/__REPORT__/options') }}"
                    >
                        @forelse ($reports as $groupName => $groupReports)
                            <optgroup label="{{ $groupName }}">
                                @foreach ($groupReports as $report)
                                    <option value="{{ $report->report_id }}" @selected($selectedReport?->report_id === $report->report_id)>
                                        {{ $report->title }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @empty
                            <option value="">No reports available</option>
                        @endforelse
                    </select>
                    @if ($selectedReport?->description)
                        <p class="mt-1 text-xs text-gray-500">{{ $selectedReport->description }}</p>
                    @endif
                </div>
            </div>

            <div
                class="rounded-xl border border-gray-200 bg-slate-50 p-4 sm:p-5"
                data-payroll-report-options-panel
            >
                @if ($selectedReport && ! ($lazyLoadReportOptions ?? true))
                    @include(PayrollReportsModule::optionsConfig($selectedReport->options_key)['view'], [
                        'report' => $selectedReport,
                        'processedBatches' => $processedBatches,
                        'postedBatches' => $postedBatches ?? collect(),
                        'employees' => $employees ?? collect(),
                        'payYears' => $payYears ?? [],
                        'detailColumns' => $detailColumns,
                        'sortColumns' => $sortColumns,
                        'groupColumns' => $groupColumns,
                    ])
                @elseif ($selectedReport)
                    <p class="text-sm text-gray-500">Loading report options…</p>
                @else
                    <p class="text-sm text-gray-500">Select a report type to configure generation options.</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @can('payroll-reports.create')
                    <button type="submit" class="btn-primary">Generate Report</button>
                @endcan
            </div>
        </form>
    </div>
@endsection
