<form
    method="POST"
    action="{{ route('employees.upload.process') }}"
    enctype="multipart/form-data"
    class="space-y-4"
>
    @csrf

    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600">
        <p class="font-medium text-gray-900">How it works</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Download the <strong>Excel template (.xlsx)</strong> below — date and ID columns are pre-formatted as text.</li>
            <li>Fill in one row per employee starting at row 4. Do not change the first two header rows.</li>
            <li>Dates accept <strong>YYYY-MM-DD</strong> or Excel format like <strong>1/1/2000</strong>.</li>
            <li>Required: name, email, phone, campus, biometric ID, user type, salary setup, and system role.</li>
            <li>Upload the saved <strong>.xlsx</strong> file (or CSV) — preview valid rows and errors before importing.</li>
        </ol>
        <p class="mt-3 text-xs text-gray-500">
            If you use CSV instead of Excel, format date columns as <strong>Text</strong> first to avoid auto-formatting.
            Lookup columns accept IDs or labels (e.g. pay type <em>Daily</em>, role <em>staff</em>).
        </p>
        <p class="mt-3">
            <a href="{{ route('employees.upload.template') }}" class="text-[#0B318F] hover:underline" download data-no-loader>
                Download Template
            </a>
        </p>
    </div>

    <div>
        <label class="form-label">Upload File <span class="text-red-500">*</span></label>
        <input type="file" name="upload_file" accept=".xlsx,.xls,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain" class="form-input" required>
        <p class="mt-1 text-xs text-gray-500">Excel (.xlsx) recommended — also accepts CSV (.csv) or tab-delimited text (.txt), max 15 MB.</p>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Upload',
        'cancelModalId' => 'employee-upload-modal',
    ])
</form>
