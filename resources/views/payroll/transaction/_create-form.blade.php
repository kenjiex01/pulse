<form
    method="POST"
    action="{{ route('payroll.transaction.store') }}"
    class="space-y-4"
    data-payroll-batch-form
    data-pb-years='@json($batchForm['yearsByPayType'])'
    data-pb-periods='@json($batchForm['periodsByPayType'])'
>
    @csrf
    <input type="hidden" name="form_context" value="create-payroll-batch">
    @include('payroll.transaction._form', [
        'fieldIdPrefix' => 'create-payroll-batch-',
        'payTypes' => $batchForm['payTypes'],
        'taxComputations' => $batchForm['taxComputations'],
        'suggestedBatchNo' => $batchForm['suggestedBatchNo'],
        'periodsByPayType' => $batchForm['periodsByPayType'],
        'yearsByPayType' => $batchForm['yearsByPayType'],
    ])
    @include('partials.modal-form-actions', ['submitLabel' => 'Create Batch'])
</form>
