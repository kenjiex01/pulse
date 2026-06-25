@extends('layouts.app')

@section('title', 'Payroll Transaction — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Payroll Transaction',
        'description' => 'Process payroll batches, upload adjustments, and unpost posted batches.',
        'actionModalId' => match ($moduleTab) {
            'batches' => 'payroll-batch-create-modal',
            'upload-transactions' => auth()->user()?->can('payroll-transaction.create') ? 'payroll-upload-modal' : null,
            default => null,
        },
        'actionLabel' => match ($moduleTab) {
            'batches' => 'Add Payroll Batch',
            'upload-transactions' => 'Upload',
            default => null,
        },
        'actionIcon' => $moduleTab === 'batches'
            ? '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>'
            : null,
    ])

    @include('payroll.transaction._module-tabs-nav', [
        'moduleTab' => $moduleTab,
        'moduleTabs' => $moduleTabs,
    ])

    @if ($moduleTab === 'upload-transactions')
        @include('payroll.transaction._upload-tabs-nav', [
            'uploadType' => $uploadType,
            'uploadTypes' => $uploadTypes,
        ])
    @endif

    @if (in_array($moduleTab, ['batches', 'unpost-batches'], true))
        @include('partials.live-data-table', [
            'url' => route(\App\Support\PayrollTransactionModule::routeName('tab'), ['tab' => $moduleTab]),
            'search' => $search,
            'searchPlaceholder' => 'Search batch no., pay type, pay period, status, or creator...',
            'searchId' => 'payroll-transaction-search-'.$moduleTab,
            'paginator' => $batches,
            'totalLabel' => $moduleTab === 'unpost-batches' ? 'posted batches' : 'payroll batches',
            'results' => view('payroll.transaction._'.$moduleTab.'-results', [
                'moduleTab' => $moduleTab,
                'batches' => $batches,
            ])->render(),
        ])
    @elseif ($moduleTab === 'upload-transactions')
        @can('payroll-transaction.delete')
            <form
                id="payroll-upload-purge-form"
                method="POST"
                action="{{ route(\App\Support\PayrollTransactionModule::routeName('upload.destroy')) }}"
                class="mb-3 flex flex-wrap items-center gap-2"
                data-payroll-upload-purge-form
                onsubmit="return confirm('Purge selected upload batches? This cannot be undone.');"
            >
                @csrf
                @method('DELETE')
                <input type="hidden" name="upload_type" value="{{ $uploadType }}">
                <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs" disabled data-payroll-upload-purge-btn>Purge Selected</button>
                <span class="text-xs text-gray-500" data-payroll-upload-selected-count>0 selected</span>
            </form>
        @endcan

        @include('partials.live-data-table', [
            'url' => route(\App\Support\PayrollTransactionModule::routeName('tab'), ['tab' => 'upload-transactions', 'upload' => $uploadType]),
            'search' => $search,
            'searchPlaceholder' => 'Search batch no., pay type, pay period, or uploader...',
            'searchId' => 'payroll-upload-search-'.$uploadType,
            'paginator' => $uploadRecords,
            'totalLabel' => strtolower($uploadConfig['label'] ?? 'upload').' batches',
            'results' => view('payroll.transaction._upload-transactions-results', [
                'uploadType' => $uploadType,
                'uploadConfig' => $uploadConfig,
                'uploadRecords' => $uploadRecords,
            ])->render(),
        ])
    @endif

    @if ($moduleTab === 'batches' && $batchForm)
        @include('partials.modal', [
            'id' => 'payroll-batch-create-modal',
            'title' => 'Add Payroll Batch',
            'description' => 'Create a new payroll batch for a pay period.',
            'open' => $openCreateBatch ?? false,
            'panelClass' => 'modal-panel-lg',
            'body' => view('payroll.transaction._create-form', ['batchForm' => $batchForm])->render(),
        ])
    @endif

    @if ($moduleTab === 'batches' && ($viewBatch ?? null))
        @include('partials.modal', [
            'id' => 'payroll-batch-view-modal',
            'title' => 'Payroll Batch — Batch No. '.$viewBatch->formattedBatchNo(),
            'description' => 'View employees in this batch and manage assignments.',
            'open' => true,
            'panelClass' => 'max-w-5xl',
            'body' => view('payroll.transaction._show-content', [
                'batch' => $viewBatch,
                'batchEmployees' => $batchEmployees,
                'batchEditable' => $batchEditable ?? false,
                'batchEmployeeSearch' => $batchEmployeeSearch ?? '',
            ])->render(),
        ])

        @if ($batchEditable ?? false)
            @include('payroll.transaction._add-employees-modal', [
                'batch' => $viewBatch,
                'eligibleEmployees' => $eligibleEmployees ?? collect(),
                'open' => $openAddEmployees ?? false,
                'addEmployeeSearch' => $addEmployeeSearch ?? '',
                'batchEmployeeSearch' => $batchEmployeeSearch ?? '',
            ])
        @endif

        @if ($viewBatchDetail ?? null)
            @include('partials.modal', [
                'id' => 'payroll-batch-employee-detail-modal',
                'title' => 'Employee Payroll Detail',
                'description' => 'Review income and deduction lines for this employee in the batch.',
                'open' => true,
                'panelClass' => 'max-w-4xl',
                'body' => view('payroll.transaction._batch-employee-detail-content', [
                    'batch' => $viewBatch,
                    'detail' => $viewBatchDetail,
                    'activeTab' => $batchDetailTab ?? 'incomes',
                ])->render(),
            ])
        @endif
    @endif

    @if ($moduleTab === 'upload-transactions' && ($uploadConfig ?? null))
        @can('payroll-transaction.create')
            @include('partials.modal', [
                'id' => 'payroll-upload-modal',
                'title' => 'Upload '.$uploadConfig['label'],
                'description' => 'Select pay period and upload a CSV file.',
                'open' => $openUpload ?? false,
                'panelClass' => 'max-w-2xl',
                'body' => view('payroll.transaction._upload-form', [
                    'uploadType' => $uploadType,
                    'uploadConfig' => $uploadConfig,
                    'batchForm' => $batchForm,
                ])->render(),
            ])

            @if ($openPreview ?? false)
                @include('partials.modal', [
                    'id' => 'payroll-upload-preview-modal',
                    'title' => 'Upload Preview',
                    'description' => 'Review valid and invalid rows before loading to the database.',
                    'open' => true,
                    'panelClass' => 'max-w-4xl',
                    'body' => view('payroll.transaction._upload-preview', [
                        'uploadType' => $uploadType,
                        'uploadConfig' => $uploadConfig,
                        'staging' => $staging ?? null,
                        'stagingToken' => $stagingToken ?? null,
                    ])->render(),
                ])
            @endif
        @endcan

        @if ($viewUploadTransaction ?? null)
            @include('partials.modal', [
                'id' => 'payroll-upload-view-modal',
                'title' => 'Upload Batch #'.$viewUploadTransaction->batch_no,
                'description' => $uploadConfig['label'].' · '.$viewUploadTransaction->filename,
                'open' => true,
                'panelClass' => 'max-w-5xl',
                'body' => view('payroll.transaction._upload-show-content', [
                    'uploadType' => $uploadType,
                    'uploadConfig' => $uploadConfig,
                    'transaction' => $viewUploadTransaction,
                ])->render(),
            ])
        @endif
    @endif
@endsection
