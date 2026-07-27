@extends('layouts.app')

@section('title', 'Time Logs — '.config('app.name'))

@section('content')
    @php
        $isTeachingLoads = $isTeachingLoads ?? false;
    @endphp

    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Time Logs',
        'description' => $isTeachingLoads
            ? 'Pull faculty teaching loads from Skolaris and review pull history by batch and employee.'
            : 'Upload and manage raw time in / time out and DTR timelog transactions from biometric or file imports.',
        'actionModalId' => auth()->user()?->can('time-logs.create')
            ? ($isTeachingLoads ? 'time-logs-pull-modal' : 'time-logs-upload-modal')
            : null,
        'actionLabel' => $isTeachingLoads ? 'Pull from Skolaris' : 'Upload',
    ])

    <div class="employee-tabs-shell mb-4">
        <nav class="flex flex-wrap gap-1" role="tablist">
            @foreach ($tabs as $tabKey => $tabLabel)
                <a
                    href="{{ route(\App\Support\TimeLogs::routeName('tab'), ['tab' => $tabKey, 'search' => $search ?: null]) }}"
                    role="tab"
                    class="employee-tab-btn {{ $tab === $tabKey ? 'employee-tab-btn-active' : '' }}"
                    aria-selected="{{ $tab === $tabKey ? 'true' : 'false' }}"
                >
                    {{ $tabLabel }}
                </a>
            @endforeach
        </nav>
    </div>

    @if (! $isTeachingLoads)
        @can('time-logs.delete')
            <form
                id="time-logs-purge-form"
                method="POST"
                action="{{ route(\App\Support\TimeLogs::routeName('destroy'), ['tab' => $tab]) }}"
                class="mb-3 flex flex-wrap items-center gap-2"
                data-time-logs-purge-form
                onsubmit="return confirm('Purge selected upload batches? This cannot be undone.');"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs" disabled data-time-logs-purge-btn>Purge Selected</button>
                <span class="text-xs text-gray-500" data-time-logs-selected-count>0 selected</span>
            </form>
        @endcan
    @endif

    @include('partials.live-data-table', [
        'url' => route(\App\Support\TimeLogs::routeName('tab'), ['tab' => $tab]),
        'search' => $search,
        'searchPlaceholder' => $isTeachingLoads
            ? 'Search pull batch...'
            : 'Search batches, file names, or uploader...',
        'searchId' => 'time-logs-search-'.$tab,
        'paginator' => $records,
        'totalLabel' => $isTeachingLoads ? 'pull batches' : 'upload batches',
        'results' => view($isTeachingLoads ? 'timekeeping.time-logs._teaching-loads-results' : 'timekeeping.time-logs._results', [
            'tab' => $tab,
            'config' => $config,
            'records' => $records,
            'skolarisListError' => $skolarisListError ?? null,
        ])->render(),
    ])

    @can('time-logs.create')
        @if ($isTeachingLoads)
            @include('partials.modal', [
                'id' => 'time-logs-pull-modal',
                'title' => 'Pull Teaching Loads from Skolaris',
                'description' => 'Select a date range and faculty employees. Skolaris matching is verified during pull.',
                'open' => $openPull ?? false,
                'panelClass' => 'max-w-2xl',
                'body' => view('timekeeping.time-logs._pull-form', [
                    'pullEmployees' => $pullEmployees ?? collect(),
                    'pullSearch' => $pullSearch ?? '',
                    'skolarisListError' => $skolarisListError ?? null,
                ])->render(),
            ])
        @else
            @include('partials.modal', [
                'id' => 'time-logs-upload-modal',
                'title' => ($requiresCampus ?? false) ? 'Upload Timelogs DTR' : 'Upload Time Logs',
                'description' => ($requiresCampus ?? false)
                    ? 'Select campus and upload the campus Excel DTR file.'
                    : 'Select a format type and upload a tab-delimited text file.',
                'open' => $openUpload ?? false,
                'panelClass' => 'max-w-2xl',
                'body' => view('timekeeping.time-logs._upload-form', [
                    'tab' => $tab,
                    'formats' => $formats,
                    'dtrCampuses' => $dtrCampuses ?? collect(),
                    'requiresCampus' => $requiresCampus ?? false,
                ])->render(),
            ])

            @if ($openPreview ?? false)
                @include('partials.modal', [
                    'id' => 'time-logs-preview-modal',
                    'title' => 'Upload Preview',
                    'description' => 'Review valid and invalid rows before loading to the database.',
                    'open' => true,
                    'panelClass' => 'max-w-4xl',
                    'body' => view('timekeeping.time-logs._upload-preview', [
                        'tab' => $tab,
                        'staging' => $staging ?? null,
                        'stagingToken' => $stagingToken ?? null,
                        'previewFormat' => $previewFormat ?? null,
                        'previewCampus' => $previewCampus ?? null,
                        'isDtrStaging' => $isDtrStaging ?? false,
                    ])->render(),
                ])
            @endif
        @endif
    @endcan

    @if ($viewTransaction ?? null)
        @include('partials.modal', [
            'id' => 'time-logs-view-modal',
            'title' => 'Batch #'.$viewTransaction->batch_no,
            'description' => $viewTransaction->filename,
            'open' => true,
            'panelClass' => 'max-w-5xl',
            'body' => view('timekeeping.time-logs._show-content', [
                'tab' => $tab,
                'config' => $config,
                'transaction' => $viewTransaction,
            ])->render(),
        ])
    @endif

    @if (($viewPullBatch ?? null) && $isTeachingLoads)
        @php
            $employeeSummaries = $viewPullBatch->sessions
                ->groupBy('employee_id')
                ->map(function ($rows) {
                    $first = $rows->first();

                    return [
                        'employee_id' => $first?->employee_id,
                        'employee_number' => $first?->employee_number ?? '—',
                        'employee_name' => $first?->employee?->full_name ?? '—',
                        'rows_count' => $rows->count(),
                    ];
                })
                ->sortBy('employee_name')
                ->values();
        @endphp
        @include('partials.modal', [
            'id' => 'teaching-load-pull-batch-modal',
            'title' => 'Teaching Load Pull Batch #'.$viewPullBatch->formattedBatchNo(),
            'description' => 'Employees included in this pull from '.$viewPullBatch->dateRangeLabel(),
            'open' => true,
            'panelClass' => 'max-w-5xl',
            'body' => view('timekeeping.time-logs._teaching-load-pull-batch-content', [
                'batch' => $viewPullBatch,
                'employeeSummaries' => $employeeSummaries,
            ])->render(),
        ])
    @endif

    @if (($viewPullEmployee ?? null) && $isTeachingLoads)
        @include('partials.modal', [
            'id' => 'teaching-load-employee-modal',
            'title' => 'Teaching Load Rows — '.($viewPullEmployee->full_name ?? 'Employee'),
            'description' => ($viewPullEmployeeSummary?->dateRangeLabel() ?? 'Pulled rows').' • '.(($viewPullEmployeeSummary?->formattedBatchNo()) ? 'Batch #'.$viewPullEmployeeSummary->formattedBatchNo() : ''),
            'open' => true,
            'panelClass' => 'max-w-6xl',
            'backUrl' => ($viewPullEmployeeSummary?->teaching_load_pull_batch_id)
                ? route(\App\Support\TimeLogs::routeName('tab'), [
                    'tab' => \App\Support\TimeLogs::TEACHING_LOADS_TAB,
                    'view_pull' => $viewPullEmployeeSummary->teaching_load_pull_batch_id,
                    'search' => request('search'),
                ])
                : null,
            'body' => view('timekeeping.time-logs._teaching-load-employee-content', [
                'employee' => $viewPullEmployee,
                'batch' => $viewPullEmployeeSummary,
                'rows' => $viewPullEmployeeRows,
            ])->render(),
        ])
    @endif
@endsection
