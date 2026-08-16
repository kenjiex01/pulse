@php
    use App\Support\TimekeepingEmployeeProfile;
@endphp

<form
    method="POST"
    action="{{ route(TimekeepingEmployeeProfile::routeName('upload.process')) }}"
    enctype="multipart/form-data"
    class="space-y-4"
>
    @csrf

    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600">
        <p class="font-medium text-gray-900">How it works</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Download the template — it is pre-filled with all employees and their current setup.</li>
            <li>Edit holiday group, policy, shift code, rest days, and flags for the employees you want to update.</li>
            <li>Remove rows you do not want to change, or leave values as-is to keep current setup.</li>
            <li>Save as <strong>CSV (*.csv)</strong> and upload it below.</li>
        </ol>
        <p class="mt-3">
            <a
                href="{{ route(TimekeepingEmployeeProfile::routeName('upload.template')) }}"
                class="text-[#0B318F] hover:underline"
                data-no-loader
            >
                Download Template
            </a>
        </p>
    </div>

    <div>
        <label class="form-label">Upload File <span class="text-red-500">*</span></label>
        <input type="file" name="upload_file" accept=".csv,.txt,text/csv,text/plain" class="form-input" required>
        <p class="mt-1 text-xs text-gray-500">Filled CSV (.csv) or tab-delimited text (.txt) only — max 15 MB.</p>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Upload',
        'cancelModalId' => 'employee-profile-upload-modal',
    ])
</form>
