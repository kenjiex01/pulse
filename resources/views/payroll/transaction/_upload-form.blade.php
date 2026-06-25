@php
    use App\Support\PayrollTransactionModule;
@endphp

<form
    method="POST"
    action="{{ route(PayrollTransactionModule::routeName('upload.process')) }}"
    enctype="multipart/form-data"
    class="space-y-4"
    data-payroll-upload-form
    data-payroll-batch-form
    data-pb-years='@json($batchForm['yearsByPayType'])'
    data-pb-periods='@json($batchForm['periodsByPayType'])'
>
    @csrf
    <input type="hidden" name="upload_type" value="{{ $uploadType }}">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Pay Type <span class="text-red-500">*</span></label>
            <select name="pay_type_id" class="form-input" required data-pb-pay-type>
                <option value="">Select pay type</option>
                @foreach ($batchForm['payTypes'] as $payType)
                    <option value="{{ $payType->pay_type_id }}" @selected((string) old('pay_type_id') === (string) $payType->pay_type_id)>
                        {{ $payType->pay_type }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Pay Year <span class="text-red-500">*</span></label>
            <select name="pay_year" class="form-input" required data-pb-pay-year disabled>
                <option value="">Select pay year</option>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="form-label">Pay Period <span class="text-red-500">*</span></label>
            <select name="payroll_calendar_id" class="form-input" required data-pb-pay-period disabled>
                <option value="">Select pay period</option>
            </select>
        </div>
    </div>

    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600">
        <p class="font-medium text-gray-900">Before uploading</p>
        @if (! empty($uploadConfig['description']))
            <p class="mt-2 text-gray-600">{{ $uploadConfig['description'] }}</p>
        @endif
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Select pay type, pay year, and pay period (unposted periods only).</li>
            <li>Download the CSV template for {{ $uploadConfig['label'] }} (select a pay period first to pre-fill Employee No. rows from the payroll batch).</li>
            <li>Open the template in Excel. Row 1 = field names (keep). Row 2 = column labels (remove). Row 3 = data-type hints (remove).</li>
            <li>Enter your data starting on row 2 after removing the label and hint rows.</li>
            <li>Save as <strong>CSV (*.csv)</strong> or tab-delimited text (*.txt).</li>
        </ol>
        <p class="mt-3 text-xs text-gray-500">
            <strong>Note:</strong> Each row must include a valid Employee No.
        </p>
        <p class="mt-3">
            <a
                href="{{ route(PayrollTransactionModule::routeName('upload.template'), $uploadType) }}"
                class="text-[#0B318F] hover:underline"
                target="_blank"
                rel="noopener"
                data-payroll-upload-template-link
                data-payroll-upload-template-base="{{ route(PayrollTransactionModule::routeName('upload.template'), $uploadType) }}"
            >
                Download Template
            </a>
        </p>
    </div>

    <div>
        <label class="form-label">Upload File <span class="text-red-500">*</span></label>
        <input type="file" name="upload_file" accept=".txt,.csv,text/plain,text/csv" class="form-input" required>
        <p class="mt-1 text-xs text-gray-500">CSV (.csv) or tab-delimited text (.txt) only — max 15 MB.</p>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Upload',
        'cancelModalId' => 'payroll-upload-modal',
    ])
</form>
