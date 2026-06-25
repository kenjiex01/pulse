@extends('layouts.app')

@section('title', 'Time Logs — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Time Logs',
        'description' => 'Upload and manage raw time in / time out transactions from biometric or file imports.',
        'actionModalId' => auth()->user()?->can('time-logs.create') ? 'time-logs-upload-modal' : null,
        'actionLabel' => 'Upload',
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

    @include('partials.live-data-table', [
        'url' => route(\App\Support\TimeLogs::routeName('tab'), ['tab' => $tab]),
        'search' => $search,
        'searchPlaceholder' => 'Search batches, file names, or uploader...',
        'searchId' => 'time-logs-search-'.$tab,
        'paginator' => $records,
        'totalLabel' => 'upload batches',
        'results' => view('timekeeping.time-logs._results', [
            'tab' => $tab,
            'config' => $config,
            'records' => $records,
        ])->render(),
    ])

    @can('time-logs.create')
        @include('partials.modal', [
            'id' => 'time-logs-upload-modal',
            'title' => 'Upload Time Logs',
            'description' => 'Select a format type and upload a tab-delimited text file.',
            'open' => $openUpload ?? false,
            'panelClass' => 'max-w-2xl',
            'body' => view('timekeeping.time-logs._upload-form', ['formats' => $formats])->render(),
        ])

        @if ($openPreview ?? false)
            @include('partials.modal', [
                'id' => 'time-logs-preview-modal',
                'title' => 'Upload Preview',
                'description' => 'Review valid and invalid rows before loading to the database.',
                'open' => true,
                'panelClass' => 'max-w-4xl',
                'body' => view('timekeeping.time-logs._upload-preview', [
                    'staging' => $staging ?? null,
                    'stagingToken' => $stagingToken ?? null,
                    'previewFormat' => $previewFormat ?? null,
                ])->render(),
            ])
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
@endsection
