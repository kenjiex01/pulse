@php
    use App\Support\TimekeepingEmployeeLoad;
@endphp

<form
    method="POST"
    action="{{ route(TimekeepingEmployeeLoad::routeName('upload.process')) }}"
    enctype="multipart/form-data"
    class="space-y-4"
    data-employee-load-upload-form
>
    @csrf

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Date From <span class="text-red-500">*</span></label>
            <input type="date" name="date_from" class="form-input" required value="{{ old('date_from') }}" data-el-date-from>
        </div>
        <div>
            <label class="form-label">Date To <span class="text-red-500">*</span></label>
            <input type="date" name="date_to" class="form-input" required value="{{ old('date_to') }}" data-el-date-to>
        </div>
    </div>

    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600">
        <p class="font-medium text-gray-900">How it works</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Select the date range you want to record — the matching loading period is detected automatically.</li>
            <li>Download the template — it comes pre-filled with each faculty's class sessions (one row per session date).</li>
            <li>Fill in <strong>Time In</strong>, <strong>Time Out</strong>, and remarks. Do not change the pre-filled or hidden columns.</li>
            <li>Save as <strong>CSV (*.csv)</strong> and upload it below.</li>
        </ol>
        <p class="mt-3">
            <a
                href="{{ route(TimekeepingEmployeeLoad::routeName('template')) }}"
                class="text-[#0B318F] hover:underline data-[disabled=true]:pointer-events-none data-[disabled=true]:text-gray-400"
                data-el-template-link
                data-disabled="true"
                data-no-loader
                data-el-template-base="{{ route(TimekeepingEmployeeLoad::routeName('template')) }}"
            >
                Download Template
            </a>
            <span class="ml-1 text-xs text-gray-400" data-el-template-hint>(select a date range first)</span>
        </p>
    </div>

    <div>
        <label class="form-label">Upload File <span class="text-red-500">*</span></label>
        <input type="file" name="upload_file" accept=".csv,.txt,text/csv,text/plain" class="form-input" required>
        <p class="mt-1 text-xs text-gray-500">Filled CSV (.csv) or tab-delimited text (.txt) only — max 15 MB.</p>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Upload',
        'cancelModalId' => 'employee-load-upload-modal',
    ])
</form>
