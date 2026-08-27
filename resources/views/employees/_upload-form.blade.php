<form
    method="POST"
    action="{{ route('employees.upload.process') }}"
    enctype="multipart/form-data"
    class="space-y-4"
    data-employee-upload-form
>
    @csrf

    @php
        $uploadTypes = app(\App\Services\EmployeeUploadService::class)->uploadTypes();
        $selectedUploadType = old('upload_type', 'master-file');
    @endphp

    <div>
        <label class="form-label" for="employee-upload-type">Upload Type <span class="text-red-500">*</span></label>
        <select
            id="employee-upload-type"
            name="upload_type"
            class="form-input"
            required
            data-employee-upload-type
        >
            @foreach ($uploadTypes as $typeKey => $typeMeta)
                <option value="{{ $typeKey }}" @selected($selectedUploadType === $typeKey)>
                    {{ $typeMeta['label'] ?? $typeKey }}
                </option>
            @endforeach
        </select>
    </div>

    <div
        class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600"
        data-employee-upload-panel="master-file"
        @unless($selectedUploadType === 'master-file') hidden @endunless
    >
        <p class="font-medium text-gray-900">How it works — Master File</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Download the <strong>Excel template (.xlsx)</strong> below — date and ID columns are pre-formatted as text.</li>
            <li>Fill in one row per employee starting at row 4. Do not change the first two header rows.</li>
            <li>Dates accept <strong>YYYY-MM-DD</strong> or Excel format like <strong>1/1/2000</strong>.</li>
            <li>
                Match key: <strong>Employee Number + Email</strong>. Duplicate emails are not allowed.
                If both match an existing employee, that row <strong>updates</strong> the record; otherwise it creates a new one.
            </li>
            <li>Required (unless Disable required fields is checked): name, email, phone, <strong>one</strong> campus + biometric ID, user type, salary setup, and system role. Extra campuses (up to 5 total) are optional.</li>
            <li>Upload the saved <strong>.xlsx</strong> file (or CSV) — preview valid rows and errors before importing.</li>
        </ol>
        <p class="mt-3 text-xs text-gray-500">
            If you use CSV instead of Excel, format date columns as <strong>Text</strong> first to avoid auto-formatting.
            Lookup columns accept IDs or labels (e.g. pay type <em>Daily</em>, role <em>staff</em>).
        </p>
    </div>

    <label
        class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800"
        data-employee-upload-panel="master-file"
        data-employee-upload-disable-required-wrap
        @unless($selectedUploadType === 'master-file') hidden @endunless
    >
        <input type="hidden" name="disable_required_fields" value="0">
        <input
            type="checkbox"
            name="disable_required_fields"
            value="1"
            class="mt-1"
            @checked(old('disable_required_fields'))
            data-employee-upload-disable-required
        >
        <span class="flex-1">
            <span class="font-medium text-gray-900">Disable required fields</span>
            <span class="mt-1 block text-xs text-gray-500">
                When checked, only filled columns are validated and saved. Employee Number and Email are still required as the unique match key.
            </span>
        </span>
    </label>

    <div
        class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600"
        data-employee-upload-panel="employee-salary"
        @unless($selectedUploadType === 'employee-salary') hidden @endunless
    >
        <p class="font-medium text-gray-900">How it works — Employee Salary</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Download the <strong>Employee Salary template (.xlsx)</strong> below.</li>
            <li>One row per salary update for an <strong>existing employee</strong> starting at row 4.</li>
            <li>Required: <strong>Employee Number</strong>, <strong>Employment Slot</strong> (1=primary, 2=secondary/hybrid), pay type, basic computation, and rate group.</li>
            <li>Salary history is preserved when effectivity dates or amounts change.</li>
            <li>Preview valid rows and errors before applying salary updates.</li>
        </ol>
        <p class="mt-3 text-xs text-gray-500">
            Use employment slot <strong>2</strong> only for hybrid employees with a second employment record.
        </p>
    </div>

    <div
        class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600"
        data-employee-upload-panel="employee-assignment"
        @unless($selectedUploadType === 'employee-assignment') hidden @endunless
    >
        <p class="font-medium text-gray-900">How it works — Employee Assignment</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Download the <strong>Employee Assignment template (.xlsx)</strong> — it is prefilled with all current employees.</li>
            <li>The <strong>Main Campus Code</strong> column shows the campus currently marked as that employee’s main assignment.</li>
            <li>Change the campus code to another campus <strong>already assigned</strong> to that employee. Do not change the first two header rows.</li>
            <li>On import, the uploaded campus becomes the new main assignment. The previous main campus is unchecked.</li>
            <li>Preview valid rows and errors before applying.</li>
        </ol>
        <p class="mt-3 text-xs text-gray-500">
            This upload does not add a new campus. Add extra campuses on the employee profile first, then mark one as main here.
        </p>
    </div>

    <p class="text-sm">
        <a
            href="{{ route('employees.upload.template', ['type' => $selectedUploadType]) }}"
            class="text-[#0B318F] hover:underline"
            download
            data-no-loader
            data-employee-upload-template-link
            data-employee-upload-template-base="{{ route('employees.upload.template') }}"
        >
            Download Template
        </a>
    </p>

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
