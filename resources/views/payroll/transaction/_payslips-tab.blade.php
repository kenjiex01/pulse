@php
    use App\Services\Reports\ReportBatchOptionsService;
    use App\Support\PayrollTransactionModule;
@endphp

<div
    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
    data-payslip-send-root
    data-payslip-send-url="{{ route(PayrollTransactionModule::routeName('payslips.send')) }}"
>
    <div class="space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Send Payslip Email</h2>
            <p class="mt-1 text-sm text-gray-600">
                Select a <strong>posted</strong> payroll batch and one or more employees. Each employee receives their payslip as a PDF attachment.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div>
                <label for="transaction-payslip-batch-id" class="form-label">Posted Payroll Batch</label>
                <select
                    id="transaction-payslip-batch-id"
                    name="payroll_batch_id"
                    class="form-input"
                    required
                    data-payslip-batch-select
                    data-employees-url="{{ route('payroll.reports.batch-employees') }}"
                >
                    <option value="">Select posted batch…</option>
                    @foreach (($postedBatches ?? collect()) as $batch)
                        <option value="{{ $batch->payroll_batch_id }}">
                            {{ app(ReportBatchOptionsService::class)->batchLabel($batch) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="transaction-payslip-employee-ids" class="form-label">Employee(s)</label>
                <select
                    id="transaction-payslip-employee-ids"
                    name="employee_ids[]"
                    class="form-input min-h-48"
                    multiple
                    required
                    data-payslip-employee-select
                >
                    <option value="" disabled>Select a posted batch to load employees…</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple employees from the chosen batch.</p>
            </div>
        </div>

        <div class="hidden space-y-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-3" data-payslip-progress-panel>
            <div class="flex items-center justify-between text-sm font-medium text-[#0B318F]">
                <span>Sending payslip emails…</span>
                <span data-payslip-progress-label>0 / 0</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-white">
                <div class="h-full rounded-full bg-[#00A3E6] transition-all duration-300" style="width: 0%" data-payslip-progress-bar></div>
            </div>
            <p class="text-xs text-gray-600" data-payslip-progress-detail></p>
        </div>

        <p class="hidden text-sm text-red-600" data-payslip-send-error></p>
        <p class="hidden text-sm text-green-700" data-payslip-send-success></p>

        <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
            <button
                type="button"
                class="btn-primary w-full sm:w-auto"
                data-payslip-send-btn
            >
                Send Email
            </button>
        </div>
    </div>
</div>
