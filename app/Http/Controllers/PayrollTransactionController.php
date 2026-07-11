<?php

namespace App\Http\Controllers;

use App\Models\DeductionType;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\RawPayrollTransaction;
use App\Services\PayrollBatchService;
use App\Services\PayrollTransactionUploadService;
use App\Services\SysLogService;
use App\Support\LiveTable;
use App\Support\PayrollTransactionModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollTransactionController extends Controller
{
    public function __construct(
        private readonly PayrollBatchService $batchService,
        private readonly PayrollTransactionUploadService $uploadService,
    ) {}

    public function index(Request $request, string $tab): View|RedirectResponse
    {
        $tab = PayrollTransactionModule::resolveTab($tab);
        PayrollTransactionModule::authorize($request->user(), 'view');

        $uploadType = PayrollTransactionModule::DEFAULT_UPLOAD_TYPE;

        if ($tab === 'upload-transactions') {
            $uploadType = PayrollTransactionModule::resolveUploadType($request->input('upload'));
        }

        $search = $request->string('search')->trim()->toString();
        $batches = null;
        $batchForm = null;
        $openCreateBatch = false;
        $viewBatch = null;
        $batchEmployees = null;
        $eligibleEmployees = null;
        $openAddEmployees = false;
        $batchEmployeeSearch = $request->string('batch_employee_search')->trim()->toString();
        $addEmployeeSearch = $request->string('add_employee_search')->trim()->toString();
        $addEmployeesEmptyMessage = null;
        $viewBatchDetail = null;
        $batchDetailTab = PayrollTransactionModule::resolveBatchDetailTab($request->input('batch_detail_tab'));
        $incomeTypes = collect();
        $deductionTypes = collect();
        $openAddEmployeeIncome = false;
        $openAddEmployeeDeduction = false;
        $uploadRecords = null;
        $uploadConfig = null;
        $openUpload = false;
        $openPreview = false;
        $staging = null;
        $stagingToken = null;
        $viewUploadTransaction = null;

        if (in_array($tab, ['batches', 'unpost-batches'], true)) {
            $batches = PayrollBatch::query()
                ->with(['payrollCalendar.payType', 'status', 'createdBy'])
                ->withCount('details')
                ->when($tab === 'unpost-batches', function ($query) {
                    $query->where('payroll_batch_status_id', PayrollBatchStatus::POSTED);
                })
                ->when($tab === 'batches', function ($query) {
                    $query->where('payroll_batch_status_id', '!=', PayrollBatchStatus::POSTED);
                })
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery
                            ->where('batch_no', 'like', '%'.$search.'%')
                            ->orWhereHas('payrollCalendar', function ($calendarQuery) use ($search) {
                                $calendarQuery
                                    ->where('pay_year', 'like', '%'.$search.'%')
                                    ->orWhere('pay_period', 'like', '%'.$search.'%');
                            })
                            ->orWhereHas('payrollCalendar.payType', fn ($payTypeQuery) => $payTypeQuery->where('pay_type', 'like', '%'.$search.'%'))
                            ->orWhereHas('status', fn ($statusQuery) => $statusQuery->where('payroll_batch_status', 'like', '%'.$search.'%'))
                            ->orWhereHas('createdBy', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'));
                    });
                })
                ->orderByDesc('payroll_batch_id')
                ->paginate(LiveTable::perPage($request, 15))
                ->withQueryString();

            if ($tab === 'batches') {
                $batchForm = $this->batchService->formData();
                $openCreateBatch = ($request->session()->get('errors')?->any() && old('form_context') === 'create-payroll-batch')
                    || $request->boolean('create');

                $viewBatchId = (int) $request->input('view_payroll_batch', old('payroll_batch_id'));

                if ($viewBatchId > 0) {
                    $viewBatch = $this->batchService->loadBatchForView($viewBatchId);

                    // Posted batches belong on Unpost Batches, not the working list.
                    if (
                        $viewBatch
                        && (int) $viewBatch->payroll_batch_status_id === PayrollBatchStatus::POSTED
                    ) {
                        return redirect()
                            ->route(PayrollTransactionModule::routeName('tab'), [
                                'tab' => 'unpost-batches',
                            ]);
                    }

                    if ($viewBatch) {
                        $batchEmployees = $this->batchService
                            ->batchEmployeesQuery($viewBatch, $batchEmployeeSearch !== '' ? $batchEmployeeSearch : null)
                            ->paginate(LiveTable::perPage($request, 25), ['*'], 'batch_employee_page')
                            ->withQueryString();

                        $openAddEmployees = $request->boolean('add_employees')
                            || ($request->session()->get('errors')?->any() && old('form_context') === 'add-payroll-batch-employees');

                        if ($this->batchService->isBatchEditable($viewBatch)) {
                            $eligibleEmployees = $this->batchService
                                ->eligibleEmployeesQuery($viewBatch, $addEmployeeSearch !== '' ? $addEmployeeSearch : null)
                                ->limit(200)
                                ->get();

                            if ($eligibleEmployees->isEmpty()) {
                                $addEmployeesEmptyMessage = $this->batchService->addEmployeesEmptyMessage(
                                    $viewBatch,
                                    $addEmployeeSearch !== '',
                                );
                            }
                        }

                        $viewBatchDetailId = (int) $request->input('view_batch_detail', 0);

                        if ($viewBatchDetailId > 0) {
                            $viewBatchDetail = $this->batchService->loadBatchDetailForView(
                                $viewBatchDetailId,
                                $viewBatch->payroll_batch_id,
                            );

                            if ($viewBatchDetail) {
                                if (! $request->ajax()) {
                                    SysLogService::record(
                                        action: 'read',
                                        table: 'trn_payroll_batch_details',
                                        recordId: $viewBatchDetail->payroll_batch_detail_id,
                                        description: 'Viewed payroll batch employee '
                                            .($viewBatchDetail->employee?->employee_number ?? $viewBatchDetail->employee_id)
                                            .' in batch no. '.$viewBatch->formattedBatchNo(),
                                    );
                                }

                                $incomeTypes = IncomeType::query()
                                    ->where('is_active', true)
                                    ->orderBy('income_type_code')
                                    ->get();

                                $deductionTypes = DeductionType::query()
                                    ->where('is_active', true)
                                    ->where(function ($query) {
                                        $query->where('is_valid_govt_deduction', false)
                                            ->orWhereNull('is_valid_govt_deduction');
                                    })
                                    ->orderBy('deduction_type_code')
                                    ->get();

                                $openAddEmployeeIncome = $request->boolean('add_income')
                                    || (
                                        $request->session()->get('errors')?->any()
                                        && old('form_context') === 'add-batch-employee-income'
                                        && (int) old('payroll_batch_detail_id') === $viewBatchDetail->payroll_batch_detail_id
                                    );

                                $openAddEmployeeDeduction = $request->boolean('add_deduction')
                                    || (
                                        $request->session()->get('errors')?->any()
                                        && old('form_context') === 'add-batch-employee-deduction'
                                        && (int) old('payroll_batch_detail_id') === $viewBatchDetail->payroll_batch_detail_id
                                    );
                            }
                        }

                        if (! $request->ajax()) {
                            SysLogService::record(
                                action: 'read',
                                table: 'trn_payroll_batches',
                                recordId: $viewBatch->payroll_batch_id,
                                description: 'Viewed payroll batch no. '.$viewBatch->formattedBatchNo(),
                            );
                        }
                    }
                }
            }

            if (! $request->ajax() && ! $viewBatch) {
                SysLogService::record(
                    action: 'read',
                    table: 'trn_payroll_batches',
                    description: 'Viewed '.PayrollTransactionModule::MODULE_TABS[$tab].' ('.$batches->total().' records)',
                );
            }
        } elseif ($tab === 'upload-transactions') {
            $uploadConfig = PayrollTransactionModule::uploadConfig($uploadType);
            $uploadRecords = PayrollTransactionModule::uploadQuery($uploadType)
                ->when($search !== '', function ($query) use ($uploadConfig, $search) {
                    $query->where(function ($searchQuery) use ($uploadConfig, $search) {
                        foreach ($uploadConfig['search'] as $column) {
                            $searchQuery->orWhere($column, 'like', '%'.$search.'%');
                        }

                        $searchQuery
                            ->orWhereHas('uploadedBy', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('payrollCalendar.payType', fn ($payTypeQuery) => $payTypeQuery->where('pay_type', 'like', '%'.$search.'%'))
                            ->orWhereHas('payrollCalendar', function ($calendarQuery) use ($search) {
                                $calendarQuery
                                    ->where('pay_year', 'like', '%'.$search.'%')
                                    ->orWhere('pay_period', 'like', '%'.$search.'%');
                            });
                    });
                })
                ->orderByDesc('payroll_transaction_id')
                ->paginate(LiveTable::perPage($request, 15))
                ->withQueryString();

            $batchForm = $this->batchService->formData(forUpload: true);
            $openUpload = ($request->boolean('open_upload') || $request->boolean('create')) && ! $request->boolean('preview');
            $openPreview = $request->boolean('preview') && session('payroll_upload_staging_token');
            $stagingToken = old('staging_token', session('payroll_upload_staging_token'));

            if ($openPreview && $stagingToken) {
                $staging = $this->uploadService->getStaging($request->user(), (string) $stagingToken);
            }

            $viewUploadId = (int) $request->input('view_upload', 0);

            if ($viewUploadId > 0) {
                $viewUploadTransaction = RawPayrollTransaction::query()
                    ->where('payroll_transaction_type_id', $uploadConfig['transaction_type_id'])
                    ->with(['payrollCalendar.payType', 'uploadedBy'])
                    ->find($viewUploadId);

                if ($viewUploadTransaction) {
                    $this->loadUploadDetailRelations($viewUploadTransaction, $uploadType, $uploadConfig['detail_relation']);

                    if (! $request->ajax()) {
                        SysLogService::record(
                            action: 'read',
                            table: 'raw_payroll_transactions',
                            recordId: $viewUploadTransaction->payroll_transaction_id,
                            description: 'Viewed upload batch no. '.$viewUploadTransaction->batch_no
                                .' · '.PayrollTransactionModule::uploadTypes()[$uploadType],
                        );
                    }
                }
            }

            if (! $request->ajax() && ! $viewUploadTransaction) {
                SysLogService::record(
                    action: 'read',
                    table: 'raw_payroll_transactions',
                    description: 'Viewed Upload Adjustments · '.PayrollTransactionModule::uploadTypes()[$uploadType]
                        .' ('.$uploadRecords->total().' records)',
                );
            }
        }

        $viewData = [
            'moduleTab' => $tab,
            'moduleTabs' => PayrollTransactionModule::MODULE_TABS,
            'uploadType' => $uploadType,
            'uploadTypes' => PayrollTransactionModule::uploadTypes(),
            'search' => $search,
            'batches' => $batches,
            'batchForm' => $batchForm,
            'openCreateBatch' => $openCreateBatch,
            'viewBatch' => $viewBatch,
            'batchEmployees' => $batchEmployees,
            'eligibleEmployees' => $eligibleEmployees,
            'openAddEmployees' => $openAddEmployees,
            'batchEmployeeSearch' => $batchEmployeeSearch,
            'addEmployeeSearch' => $addEmployeeSearch,
            'addEmployeesEmptyMessage' => $addEmployeesEmptyMessage,
            'batchEditable' => $viewBatch ? $this->batchService->isBatchEditable($viewBatch) : false,
            'batchProcessable' => $viewBatch ? $this->batchService->canProcessBatch($viewBatch) : false,
            'batchReprocessable' => $viewBatch ? $this->batchService->canReprocessBatch($viewBatch) : false,
            'batchPostable' => $viewBatch ? $this->batchService->canPostBatch($viewBatch) : false,
            'viewBatchDetail' => $viewBatchDetail,
            'batchDetailTab' => $batchDetailTab,
            'incomeTypes' => $incomeTypes,
            'deductionTypes' => $deductionTypes,
            'openAddEmployeeIncome' => $openAddEmployeeIncome,
            'openAddEmployeeDeduction' => $openAddEmployeeDeduction,
            'uploadRecords' => $uploadRecords,
            'uploadConfig' => $uploadConfig,
            'openUpload' => $openUpload,
            'openPreview' => $openPreview,
            'staging' => $staging,
            'stagingToken' => $stagingToken,
            'viewUploadTransaction' => $viewUploadTransaction,
        ];

        if ($request->ajax()) {
            return view('payroll.transaction._'.$tab.'-results', $viewData);
        }

        return view('payroll.transaction.index', $viewData);
    }

    public function create(Request $request): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'add');

        SysLogService::record(
            action: 'read',
            table: 'trn_payroll_batches',
            description: 'Opened create payroll batch form',
        );

        return redirect()->route(PayrollTransactionModule::routeName('tab'), [
            'tab' => 'batches',
            'create' => 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'add');

        $validated = $this->batchService->validate($request->all());
        $validated['include_all_employees'] = $request->boolean('include_all_employees');

        $batch = $this->batchService->create($request->user(), $validated);

        SysLogService::record(
            action: 'create',
            table: 'trn_payroll_batches',
            recordId: $batch->payroll_batch_id,
            newValues: $batch->toArray(),
            description: 'Created payroll batch no. '.$batch->formattedBatchNo(),
        );

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), ['tab' => 'batches'])
            ->with('success', 'Payroll batch '.$batch->formattedBatchNo().' created successfully.');
    }

    public function show(Request $request, PayrollBatch $batch): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'view');

        return redirect()->route(PayrollTransactionModule::routeName('tab'), [
            'tab' => 'batches',
            'view_payroll_batch' => $batch->payroll_batch_id,
            'search' => $request->input('search'),
        ]);
    }

    public function showEmployeeDetail(Request $request, PayrollBatch $batch, PayrollBatchDetail $detail): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'view');

        if ((int) $detail->payroll_batch_id !== (int) $batch->payroll_batch_id) {
            abort(404);
        }

        return redirect()->route(PayrollTransactionModule::routeName('tab'), [
            'tab' => 'batches',
            'view_payroll_batch' => $batch->payroll_batch_id,
            'view_batch_detail' => $detail->payroll_batch_detail_id,
            'batch_detail_tab' => PayrollTransactionModule::resolveBatchDetailTab($request->input('batch_detail_tab')),
            'batch_employee_search' => $request->input('batch_employee_search'),
            'search' => $request->input('search'),
        ]);
    }

    public function storeEmployeeIncome(Request $request, PayrollBatch $batch, PayrollBatchDetail $detail): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'edit');

        if ((int) $detail->payroll_batch_id !== (int) $batch->payroll_batch_id) {
            abort(404);
        }

        $validated = $request->validate([
            'income_type_id' => ['required', 'integer', 'exists:tbl_income_types,income_type_id'],
            'taxable' => ['nullable', 'numeric', 'min:0'],
            'non_taxable' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $income = $this->batchService->addIncomeToDetail($batch, $detail, $validated);
        } catch (ValidationException $exception) {
            return redirect()
                ->route(PayrollTransactionModule::routeName('tab'), [
                    'tab' => 'batches',
                    'view_payroll_batch' => $batch->payroll_batch_id,
                    'view_batch_detail' => $detail->payroll_batch_detail_id,
                    'batch_detail_tab' => 'incomes',
                    'add_income' => 1,
                    'batch_employee_search' => $request->input('batch_employee_search'),
                    'search' => $request->input('search'),
                ])
                ->withErrors($exception->errors())
                ->withInput();
        }

        $detail->loadMissing('employee');
        $incomeCode = $income->incomeType?->income_type_code ?? $income->income_type_id;

        SysLogService::record(
            action: 'create',
            table: 'trn_payroll_incomes',
            recordId: $income->payroll_income_id,
            description: 'Added income '.$incomeCode.' for employee '
                .($detail->employee?->employee_number ?? $detail->employee_id)
                .' in payroll batch no. '.$batch->formattedBatchNo(),
        );

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'batches',
                'view_payroll_batch' => $batch->payroll_batch_id,
                'view_batch_detail' => $detail->payroll_batch_detail_id,
                'batch_detail_tab' => 'incomes',
                'batch_employee_search' => $request->input('batch_employee_search'),
                'search' => $request->input('search'),
            ])
            ->with('success', 'Income line added for '.$detail->employee?->full_name.'.');
    }

    public function storeEmployeeDeduction(Request $request, PayrollBatch $batch, PayrollBatchDetail $detail): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'edit');

        if ((int) $detail->payroll_batch_id !== (int) $batch->payroll_batch_id) {
            abort(404);
        }

        $validated = $request->validate([
            'deduction_type_id' => ['required', 'integer', 'exists:tbl_deduction_types,deduction_type_id'],
            'hours' => ['nullable', 'numeric', 'min:0'],
            'employee_amount' => ['nullable', 'numeric', 'min:0'],
            'employer_amount' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:45'],
            'reference_date' => ['nullable', 'date'],
        ]);

        try {
            $deduction = $this->batchService->addDeductionToDetail($batch, $detail, $validated);
        } catch (ValidationException $exception) {
            return redirect()
                ->route(PayrollTransactionModule::routeName('tab'), [
                    'tab' => 'batches',
                    'view_payroll_batch' => $batch->payroll_batch_id,
                    'view_batch_detail' => $detail->payroll_batch_detail_id,
                    'batch_detail_tab' => 'deductions',
                    'add_deduction' => 1,
                    'batch_employee_search' => $request->input('batch_employee_search'),
                    'search' => $request->input('search'),
                ])
                ->withErrors($exception->errors())
                ->withInput();
        }

        $detail->loadMissing('employee');
        $deductionCode = $deduction->deductionType?->deduction_type_code ?? $deduction->deduction_type_id;

        SysLogService::record(
            action: 'create',
            table: 'trn_payroll_deductions',
            recordId: $deduction->payroll_deduction_id,
            description: 'Added deduction '.$deductionCode.' for employee '
                .($detail->employee?->employee_number ?? $detail->employee_id)
                .' in payroll batch no. '.$batch->formattedBatchNo(),
        );

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'batches',
                'view_payroll_batch' => $batch->payroll_batch_id,
                'view_batch_detail' => $detail->payroll_batch_detail_id,
                'batch_detail_tab' => 'deductions',
                'batch_employee_search' => $request->input('batch_employee_search'),
                'search' => $request->input('search'),
            ])
            ->with('success', 'Deduction line added for '.$detail->employee?->full_name.'.');
    }

    public function storeEmployees(Request $request, PayrollBatch $batch): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'edit');

        $validated = $request->validate([
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:tbl_employees,employee_id'],
            'include_all_employees' => ['sometimes', 'boolean'],
        ]);

        try {
            $added = $this->batchService->addEmployees(
                $batch,
                $validated['employee_ids'] ?? [],
                $request->boolean('include_all_employees'),
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route(PayrollTransactionModule::routeName('tab'), [
                    'tab' => 'batches',
                    'view_payroll_batch' => $batch->payroll_batch_id,
                    'add_employees' => 1,
                    'add_employee_search' => $request->input('add_employee_search'),
                ])
                ->withErrors($exception->errors())
                ->withInput();
        }

        SysLogService::record(
            action: 'update',
            table: 'trn_payroll_batches',
            recordId: $batch->payroll_batch_id,
            description: 'Added '.$added.' employee'.($added === 1 ? '' : 's').' to payroll batch no. '.$batch->formattedBatchNo(),
        );

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'batches',
                'view_payroll_batch' => $batch->payroll_batch_id,
            ])
            ->with('success', $added.' employee'.($added === 1 ? '' : 's').' added to batch '.$batch->formattedBatchNo().'.');
    }

    public function destroyEmployees(Request $request, PayrollBatch $batch): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'edit');

        $validated = $request->validate([
            'detail_ids' => ['required', 'array', 'min:1'],
            'detail_ids.*' => ['integer', 'exists:trn_payroll_batch_details,payroll_batch_detail_id'],
        ]);

        try {
            $removed = $this->batchService->removeEmployees($batch, $validated['detail_ids']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route(PayrollTransactionModule::routeName('tab'), [
                    'tab' => 'batches',
                    'view_payroll_batch' => $batch->payroll_batch_id,
                    'batch_employee_search' => $request->input('batch_employee_search'),
                ])
                ->withErrors($exception->errors())
                ->withInput();
        }

        SysLogService::record(
            action: 'update',
            table: 'trn_payroll_batches',
            recordId: $batch->payroll_batch_id,
            description: 'Removed '.$removed.' employee'.($removed === 1 ? '' : 's').' from payroll batch no. '.$batch->formattedBatchNo(),
        );

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'batches',
                'view_payroll_batch' => $batch->payroll_batch_id,
                'batch_employee_search' => $request->input('batch_employee_search'),
            ])
            ->with('success', $removed.' employee'.($removed === 1 ? '' : 's').' removed from batch '.$batch->formattedBatchNo().'.');
    }

    public function processBatch(Request $request, PayrollBatch $batch): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'edit');

        try {
            $processed = $this->batchService->processBatch($request->user(), $batch);
        } catch (ValidationException $exception) {
            return redirect()
                ->route(PayrollTransactionModule::routeName('tab'), [
                    'tab' => 'batches',
                    'view_payroll_batch' => $batch->payroll_batch_id,
                    'batch_employee_search' => $request->input('batch_employee_search'),
                ])
                ->withErrors($exception->errors());
        }

        $batch->refresh();

        SysLogService::record(
            action: 'update',
            table: 'trn_payroll_batches',
            recordId: $batch->payroll_batch_id,
            description: 'Processed payroll batch no. '.$batch->formattedBatchNo()
                .' ('.$processed.' employee'.($processed === 1 ? '' : 's').')',
        );

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'batches',
                'view_payroll_batch' => $batch->payroll_batch_id,
                'batch_employee_search' => $request->input('batch_employee_search'),
            ])
            ->with('success', 'Payroll batch '.$batch->formattedBatchNo().' processed successfully.');
    }

    public function reprocessBatch(Request $request, PayrollBatch $batch): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'edit');

        try {
            $processed = $this->batchService->reprocessBatch($request->user(), $batch);
        } catch (ValidationException $exception) {
            return redirect()
                ->route(PayrollTransactionModule::routeName('tab'), [
                    'tab' => 'batches',
                    'view_payroll_batch' => $batch->payroll_batch_id,
                    'batch_employee_search' => $request->input('batch_employee_search'),
                ])
                ->withErrors($exception->errors());
        }

        $batch->refresh();

        SysLogService::record(
            action: 'update',
            table: 'trn_payroll_batches',
            recordId: $batch->payroll_batch_id,
            description: 'Re-processed payroll batch no. '.$batch->formattedBatchNo()
                .' ('.$processed.' employee'.($processed === 1 ? '' : 's').')',
        );

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'batches',
                'view_payroll_batch' => $batch->payroll_batch_id,
                'batch_employee_search' => $request->input('batch_employee_search'),
            ])
            ->with('success', 'Payroll batch '.$batch->formattedBatchNo().' re-processed successfully.');
    }

    public function postBatch(Request $request, PayrollBatch $batch): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'edit');

        try {
            $this->batchService->postBatch($request->user(), $batch);
        } catch (ValidationException $exception) {
            return redirect()
                ->route(PayrollTransactionModule::routeName('tab'), [
                    'tab' => 'batches',
                    'view_payroll_batch' => $batch->payroll_batch_id,
                    'batch_employee_search' => $request->input('batch_employee_search'),
                ])
                ->withErrors($exception->errors());
        }

        $batch->refresh();

        SysLogService::record(
            action: 'update',
            table: 'trn_payroll_batches',
            recordId: $batch->payroll_batch_id,
            description: 'Posted payroll batch no. '.$batch->formattedBatchNo(),
        );

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'unpost-batches',
            ])
            ->with('success', 'Payroll batch '.$batch->formattedBatchNo().' posted successfully.');
    }

    public function unpostBatch(Request $request, PayrollBatch $batch): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'edit');

        try {
            $this->batchService->unpostBatch($request->user(), $batch);
        } catch (ValidationException $exception) {
            return redirect()
                ->route(PayrollTransactionModule::routeName('tab'), [
                    'tab' => 'unpost-batches',
                    'search' => $request->input('search'),
                ])
                ->withErrors($exception->errors());
        }

        $batch->refresh();

        SysLogService::record(
            action: 'update',
            table: 'trn_payroll_batches',
            recordId: $batch->payroll_batch_id,
            description: 'Unposted payroll batch no. '.$batch->formattedBatchNo(),
        );

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'unpost-batches',
                'search' => $request->input('search'),
            ])
            ->with('success', 'Payroll batch '.$batch->formattedBatchNo().' unposted successfully.');
    }

    public function downloadUploadTemplate(Request $request, string $uploadType): StreamedResponse
    {
        $uploadType = PayrollTransactionModule::resolveUploadType($uploadType);
        PayrollTransactionModule::authorize($request->user(), 'add');

        $calendarId = (int) $request->query('payroll_calendar_id', 0);
        $prefillEmployeeNumbers = $calendarId > 0
            ? $this->batchService->employeeNumbersForCalendar($calendarId)
            : [];

        $content = $this->uploadService->buildTemplateContent($uploadType, $prefillEmployeeNumbers);
        $filename = 'payroll_upload_'.$uploadType.'_template.csv';

        return response()->streamDownload(
            fn () => print($content),
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    public function processUpload(Request $request): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'add');

        $validated = $request->validate([
            'upload_type' => ['required', 'string'],
            'pay_type_id' => ['required', 'exists:lu_pay_types,pay_type_id'],
            'pay_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'payroll_calendar_id' => ['required', 'exists:tbl_payroll_calendar,payroll_calendar_id'],
            'upload_file' => ['required', 'file', 'max:'.config('uploads.max_file_kb', 15360)],
        ]);

        $uploadType = PayrollTransactionModule::resolveUploadType($validated['upload_type']);

        $this->batchService->assertCalendarOpenForUpload((int) $validated['payroll_calendar_id']);

        try {
            $result = $this->uploadService->parseUploadedFile($request->file('upload_file'), $uploadType);
            $token = $this->uploadService->createStagingToken(
                $request->user(),
                $uploadType,
                (int) $validated['payroll_calendar_id'],
                $result,
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(PayrollTransactionModule::routeName('tab'), [
                    'tab' => 'upload-transactions',
                    'upload' => $uploadType,
                    'open_upload' => 1,
                ])
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        session(['payroll_upload_staging_token' => $token]);

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'upload-transactions',
                'upload' => $uploadType,
                'preview' => 1,
            ])
            ->with('success', 'File parsed. Review the preview before loading to the database.');
    }

    public function commitUpload(Request $request): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'add');

        $validated = $request->validate([
            'staging_token' => ['required', 'string'],
            'upload_type' => ['required', 'string'],
        ]);

        $uploadType = PayrollTransactionModule::resolveUploadType($validated['upload_type']);

        $staging = $this->uploadService->getStaging($request->user(), $validated['staging_token']);

        if ($staging) {
            $this->batchService->assertCalendarOpenForUpload((int) $staging['payroll_calendar_id']);
        }

        try {
            $transaction = $this->uploadService->commit($request->user(), $validated['staging_token']);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(PayrollTransactionModule::routeName('tab'), [
                    'tab' => 'upload-transactions',
                    'upload' => $uploadType,
                    'preview' => 1,
                ])
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        $applied = $this->batchService->applyRawUploadToOpenBatches($transaction);

        session()->forget('payroll_upload_staging_token');

        SysLogService::record(
            action: 'create',
            table: 'raw_payroll_transactions',
            recordId: $transaction->payroll_transaction_id,
            description: 'Loaded payroll upload batch no. '.$transaction->batch_no
                .' ('.$uploadType.')'
                .($applied > 0 ? '; applied '.$applied.' payroll line(s) to open payroll batch(es)' : ''),
        );

        $successMessage = 'Upload batch #'.$transaction->batch_no.' loaded successfully.';

        if ($applied > 0) {
            $successMessage .= ' Applied '.$applied.' payroll line'
                .($applied === 1 ? '' : 's').' to the matching payroll batch.';
        }

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'upload-transactions',
                'upload' => $uploadType,
                'view_upload' => $transaction->payroll_transaction_id,
            ])
            ->with('success', $successMessage);
    }

    public function discardUploadStaging(Request $request): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'add');

        $uploadType = PayrollTransactionModule::resolveUploadType($request->input('upload_type'));
        $token = (string) $request->input('staging_token', session('payroll_upload_staging_token'));

        if ($token !== '') {
            $this->uploadService->discardStaging($request->user(), $token);
        }

        session()->forget('payroll_upload_staging_token');

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'upload-transactions',
                'upload' => $uploadType,
                'open_upload' => 1,
            ])
            ->with('success', 'Upload cancelled.');
    }

    public function destroyUploadBatches(Request $request): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'delete');

        $validated = $request->validate([
            'upload_type' => ['required', 'string'],
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'exists:raw_payroll_transactions,payroll_transaction_id'],
        ]);

        $uploadType = PayrollTransactionModule::resolveUploadType($validated['upload_type']);
        $config = PayrollTransactionModule::uploadConfig($uploadType);
        $ids = $validated['selected_ids'];

        $deleted = DB::transaction(function () use ($config, $ids) {
            $records = RawPayrollTransaction::query()
                ->where('payroll_transaction_type_id', $config['transaction_type_id'])
                ->whereIn('payroll_transaction_id', $ids)
                ->get();

            foreach ($records as $record) {
                $record->delete();
            }

            return $records->count();
        });

        SysLogService::record(
            action: 'delete',
            table: 'raw_payroll_transactions',
            description: 'Purged '.$deleted.' upload batch'.($deleted === 1 ? '' : 'es')
                .' · '.PayrollTransactionModule::uploadTypes()[$uploadType],
        );

        return redirect()
            ->route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'upload-transactions',
                'upload' => $uploadType,
                'search' => $request->input('search'),
            ])
            ->with('success', $deleted.' upload batch'.($deleted === 1 ? '' : 'es').' purged.');
    }

    private function loadUploadDetailRelations(RawPayrollTransaction $transaction, string $uploadType, string $relation): void
    {
        $nested = match ($uploadType) {
            'incomes', 'income-adjustments' => ['employee', 'incomeType'],
            'deductions', 'deduction-adjustments' => ['employee', 'deductionType'],
            'hours-worked' => ['employee', 'dayType', 'timeType'],
            'leaves' => ['employee', 'leaveType'],
            'loans' => ['employee', 'loanType'],
            default => ['employee'],
        };

        $transaction->load([$relation => fn ($query) => $query->with($nested)]);
    }
}
