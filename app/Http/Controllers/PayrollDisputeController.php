<?php

namespace App\Http\Controllers;

use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollDispute;
use App\Services\PayrollDisputeService;
use App\Services\SysLogService;
use App\Support\PayrollTransactionModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayrollDisputeController extends Controller
{
    public function __construct(
        private readonly PayrollDisputeService $disputes,
    ) {}

    public function prefill(Request $request, PayrollBatch $batch, PayrollBatchDetail $detail): JsonResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'view');
        $this->assertDetailInBatch($batch, $detail);

        $validated = $request->validate([
            'work_date' => ['required', 'date_format:Y-m-d'],
            'category' => ['required', Rule::in(array_keys(PayrollDispute::CATEGORIES))],
            'ignore_dispute_id' => ['nullable', 'integer'],
        ]);

        return response()->json($this->disputes->prefill(
            $detail,
            $validated['work_date'],
            $validated['category'],
            isset($validated['ignore_dispute_id']) ? (int) $validated['ignore_dispute_id'] : null,
        ));
    }

    public function preview(Request $request, PayrollBatch $batch, PayrollBatchDetail $detail): JsonResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'view');
        $this->assertDetailInBatch($batch, $detail);

        $input = $request->only(['category', 'work_date', 'shift_code_id', 'time_in', 'time_out', 'ot_start', 'ot_end']);

        return response()->json($this->disputes->calculate($detail, $input));
    }

    public function store(Request $request, PayrollBatch $batch, PayrollBatchDetail $detail): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'edit');
        $this->assertDetailInBatch($batch, $detail);

        if (! $this->disputes->canEditDisputes($batch)) {
            return $this->redirectToDisputes($request, $batch, $detail)
                ->with('error', 'Disputes can only be filed while the batch is Pending or Processed.');
        }

        try {
            $payload = $this->disputes->validateForStore($detail, $request->all());
        } catch (ValidationException $exception) {
            return $this->redirectToDisputes($request, $batch, $detail, ['add_dispute' => 1])
                ->withErrors($exception->errors())
                ->withInput();
        }

        $dispute = PayrollDispute::query()->create([
            ...$payload['attributes'],
            'payroll_batch_id' => $batch->payroll_batch_id,
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'employee_id' => $detail->employee_id,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]);

        $detail->loadMissing('employee');

        SysLogService::record(
            action: 'create',
            table: 'trn_payroll_disputes',
            recordId: $dispute->payroll_dispute_id,
            newValues: $this->logValues($dispute),
            description: 'Filed '.$dispute->categoryName().' dispute for employee '
                .($detail->employee?->employee_number ?? $detail->employee_id)
                .' on '.$dispute->work_date?->toDateString()
                .' in payroll batch no. '.$batch->formattedBatchNo(),
        );

        return $this->redirectToDisputes($request, $batch, $detail)
            ->with('success', 'Dispute saved (expected pay '.number_format($dispute->expectedTotal(), 2).'). Process/Reprocess the batch to apply it to pay.');
    }

    public function update(Request $request, PayrollBatch $batch, PayrollBatchDetail $detail, PayrollDispute $dispute): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'edit');
        $this->assertDisputeOnDetail($batch, $detail, $dispute);

        if (! $this->disputes->canEditDisputes($batch)) {
            return $this->redirectToDisputes($request, $batch, $detail)
                ->with('error', 'Disputes can only be edited while the batch is Pending or Processed.');
        }

        try {
            $payload = $this->disputes->validateForStore($detail, $request->all(), (int) $dispute->payroll_dispute_id);
        } catch (ValidationException $exception) {
            return $this->redirectToDisputes($request, $batch, $detail, ['edit_dispute' => $dispute->payroll_dispute_id])
                ->withErrors($exception->errors())
                ->withInput();
        }

        $oldValues = $this->logValues($dispute);

        $dispute->update([
            ...$payload['attributes'],
            'updated_by_id' => $request->user()->id,
        ]);

        $detail->loadMissing('employee');

        SysLogService::record(
            action: 'update',
            table: 'trn_payroll_disputes',
            recordId: $dispute->payroll_dispute_id,
            oldValues: $oldValues,
            newValues: $this->logValues($dispute->fresh()),
            description: 'Updated '.$dispute->categoryName().' dispute for employee '
                .($detail->employee?->employee_number ?? $detail->employee_id)
                .' on '.$dispute->work_date?->toDateString()
                .' in payroll batch no. '.$batch->formattedBatchNo(),
        );

        return $this->redirectToDisputes($request, $batch, $detail)
            ->with('success', 'Dispute updated. Process/Reprocess the batch to apply it to pay.');
    }

    public function destroy(Request $request, PayrollBatch $batch, PayrollBatchDetail $detail, PayrollDispute $dispute): RedirectResponse
    {
        PayrollTransactionModule::authorize($request->user(), 'edit');
        $this->assertDisputeOnDetail($batch, $detail, $dispute);

        if (! $this->disputes->canEditDisputes($batch)) {
            return $this->redirectToDisputes($request, $batch, $detail)
                ->with('error', 'Disputes can only be removed while the batch is Pending or Processed.');
        }

        $oldValues = $this->logValues($dispute);
        $dispute->delete();
        $detail->loadMissing('employee');

        SysLogService::record(
            action: 'delete',
            table: 'trn_payroll_disputes',
            recordId: $dispute->payroll_dispute_id,
            oldValues: $oldValues,
            description: 'Removed '.$dispute->categoryName().' dispute for employee '
                .($detail->employee?->employee_number ?? $detail->employee_id)
                .' on '.($oldValues['work_date'] ?? '—')
                .' in payroll batch no. '.$batch->formattedBatchNo(),
        );

        $message = (int) $batch->payroll_batch_status_id === PayrollBatchStatus::PROCESSED
            ? 'Dispute removed. Reprocess the batch to remove it from pay.'
            : 'Dispute removed.';

        return $this->redirectToDisputes($request, $batch, $detail)->with('success', $message);
    }

    private function assertDetailInBatch(PayrollBatch $batch, PayrollBatchDetail $detail): void
    {
        if ((int) $detail->payroll_batch_id !== (int) $batch->payroll_batch_id) {
            abort(404);
        }
    }

    private function assertDisputeOnDetail(PayrollBatch $batch, PayrollBatchDetail $detail, PayrollDispute $dispute): void
    {
        $this->assertDetailInBatch($batch, $detail);

        if ((int) $dispute->payroll_batch_detail_id !== (int) $detail->payroll_batch_detail_id) {
            abort(404);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function redirectToDisputes(Request $request, PayrollBatch $batch, PayrollBatchDetail $detail, array $extra = []): RedirectResponse
    {
        return redirect()->route(PayrollTransactionModule::routeName('tab'), array_filter([
            'tab' => (int) $batch->payroll_batch_status_id === PayrollBatchStatus::POSTED ? 'unpost-batches' : 'batches',
            'view_payroll_batch' => $batch->payroll_batch_id,
            'view_batch_detail' => $detail->payroll_batch_detail_id,
            'batch_detail_tab' => 'disputes',
            'batch_employee_search' => $request->input('batch_employee_search'),
            'search' => $request->input('search'),
            ...$extra,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function logValues(PayrollDispute $dispute): array
    {
        return [
            'employee_id' => $dispute->employee_id,
            'payroll_batch_id' => $dispute->payroll_batch_id,
            'category' => $dispute->category,
            'work_date' => $dispute->work_date?->toDateString(),
            'shift_code_id' => $dispute->shift_code_id,
            'time_in' => $dispute->time_in,
            'time_out' => $dispute->time_out,
            'ot_start' => $dispute->ot_start,
            'ot_end' => $dispute->ot_end,
            'expected_pay' => (float) $dispute->expected_pay,
            'expected_ot_pay' => (float) $dispute->expected_ot_pay,
            'remarks' => $dispute->remarks,
        ];
    }
}
