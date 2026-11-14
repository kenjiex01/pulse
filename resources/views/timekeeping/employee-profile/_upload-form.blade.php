@php
    use App\Support\TimekeepingEmployeeProfile;

    $uploadType = TimekeepingEmployeeProfile::normalizeUploadType($uploadType ?? null);
@endphp

<form
    method="POST"
    action="{{ route(TimekeepingEmployeeProfile::routeName('upload.process')) }}"
    enctype="multipart/form-data"
    class="space-y-4"
    data-upload-type-root
>
    @csrf

    <div>
        <label for="employee-profile-upload-type" class="form-label">Upload Type <span class="text-red-500">*</span></label>
        <select id="employee-profile-upload-type" name="upload_type" class="form-input" data-upload-type-select>
            @foreach (TimekeepingEmployeeProfile::uploadTypes() as $typeKey => $typeLabel)
                <option value="{{ $typeKey }}" @selected($uploadType === $typeKey)>{{ $typeLabel }}</option>
            @endforeach
        </select>
    </div>

    <div
        class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600 {{ $uploadType === TimekeepingEmployeeProfile::UPLOAD_TYPE_SETUP ? '' : 'hidden' }}"
        data-upload-type-panel="{{ TimekeepingEmployeeProfile::UPLOAD_TYPE_SETUP }}"
    >
        <p class="font-medium text-gray-900">How it works</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Download the template — it is pre-filled with all employees and their current setup.</li>
            <li>Edit holiday group, policy, <strong>default shift</strong>, <strong>Mon–Sun shift columns</strong>, rest days, and flags for the employees you want to update.</li>
            <li>Rows with empty Holiday Group, Policy, and Default Shift are <strong>skipped</strong> (employee not updated). Prefilled rows keep current setup when you re-upload.</li>
            <li>Save as <strong>CSV (*.csv)</strong> and upload it below.</li>
        </ol>
        <p class="mt-3">
            <a
                href="{{ route(TimekeepingEmployeeProfile::routeName('upload.template')) }}"
                class="text-[#0B318F] hover:underline"
                download="employee_profile_upload_template.csv"
                data-no-loader
                data-pulse-file-download
            >
                Download Template
            </a>
        </p>
    </div>

    <div
        class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600 {{ $uploadType === TimekeepingEmployeeProfile::UPLOAD_TYPE_CONSULTATION ? '' : 'hidden' }}"
        data-upload-type-panel="{{ TimekeepingEmployeeProfile::UPLOAD_TYPE_CONSULTATION }}"
    >
        <p class="font-medium text-gray-900">How it works</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Download the template — it is pre-filled with all <strong>faculty</strong> and their current consultation hours.</li>
            <li>Add <strong>one row per time range</strong>: Employee No., Day (Monday–Sunday), Time Start, Time End. For two ranges on Monday, add two Monday rows.</li>
            <li>Each faculty in the file has their consultation hours <strong>replaced</strong> by the rows in the file. Faculty with no Day and times are <strong>skipped</strong> (not changed).</li>
            <li>Times can be <strong>9:00 AM</strong> or <strong>09:00</strong>. Ranges on the same day must not overlap.</li>
            <li>Save as <strong>CSV (*.csv)</strong> and upload it below. The preview flags hours that conflict with the last pulled teaching load.</li>
        </ol>
        <p class="mt-3">
            <a
                href="{{ route(TimekeepingEmployeeProfile::routeName('upload.template'), ['type' => TimekeepingEmployeeProfile::UPLOAD_TYPE_CONSULTATION]) }}"
                class="text-[#0B318F] hover:underline"
                download="consultation_hours_upload_template.csv"
                data-no-loader
                data-pulse-file-download
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
