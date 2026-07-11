@extends('layouts.app')

@section('title', 'Employee Load — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Employee Load',
        'description' => 'Download a pre-filled faculty load template, fill in Time In / Time Out, then upload it for review.',
        'actionModalId' => auth()->user()?->can('employee-load.create') ? 'employee-load-upload-modal' : null,
        'actionLabel' => 'Upload',
    ])

    @can('employee-load.delete')
        <form
            id="employee-load-purge-form"
            method="POST"
            action="{{ route(\App\Support\TimekeepingEmployeeLoad::routeName('destroy')) }}"
            class="mb-3 flex flex-wrap items-center gap-2"
            data-employee-load-purge-form
            onsubmit="return confirm('Purge selected upload batches? This cannot be undone.');"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs" disabled data-employee-load-purge-btn>Purge Selected</button>
            <span class="text-xs text-gray-500" data-employee-load-selected-count>0 selected</span>
        </form>
    @endcan

    @include('partials.live-data-table', [
        'url' => route(\App\Support\TimekeepingEmployeeLoad::routeName('index')),
        'search' => $search,
        'searchPlaceholder' => 'Search batches, file names, or enrollment period...',
        'searchId' => 'employee-load-search',
        'paginator' => $records,
        'totalLabel' => 'upload batches',
        'results' => view('timekeeping.employee-load._results', [
            'records' => $records,
            'listColumns' => $listColumns,
        ])->render(),
    ])

    @can('employee-load.create')
        @include('partials.modal', [
            'id' => 'employee-load-upload-modal',
            'title' => 'Upload Employee Load',
            'description' => 'Select a date range, download the pre-filled template, then upload the filled file.',
            'open' => $openUpload ?? false,
            'panelClass' => 'max-w-2xl',
            'body' => view('timekeeping.employee-load._upload-form')->render(),
        ])

        @if ($openPreview ?? false)
            @include('partials.modal', [
                'id' => 'employee-load-preview-modal',
                'title' => 'Upload Preview',
                'description' => 'Review valid rows, warnings, and errors before loading to the database.',
                'open' => true,
                'panelClass' => 'max-w-5xl',
                'body' => view('timekeeping.employee-load._upload-preview', [
                    'staging' => $staging ?? null,
                    'stagingToken' => $stagingToken ?? null,
                ])->render(),
            ])
        @endif
    @endcan

    @if ($viewTransaction ?? null)
        @include('partials.modal', [
            'id' => 'employee-load-view-modal',
            'title' => 'Batch #'.$viewTransaction->formattedBatchNo(),
            'description' => $viewTransaction->filename,
            'open' => true,
            'panelClass' => 'max-w-6xl',
            'body' => view('timekeeping.employee-load._show-content', [
                'transaction' => $viewTransaction,
            ])->render(),
        ])
    @endif
@endsection
