@php
    $headers = app(\App\Services\HrLookupUploadService::class)->headers($lookup);
@endphp

<form
    method="POST"
    action="{{ route(\App\Support\HrLookup::routeName($lookup, 'upload')) }}"
    enctype="multipart/form-data"
    class="space-y-4"
>
    @csrf
    <input type="hidden" name="form_context" value="upload-{{ $lookup }}">

    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600">
        <p class="font-medium text-gray-900">How it works</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Download the template.</li>
            <li>Fill one row per record. Campus columns accept a campus code or campus name. Yes/No columns accept Yes or No.</li>
            <li>Save the file and upload it below.</li>
        </ol>
        <p class="mt-3 text-xs text-gray-500">Columns: {{ implode(', ', $headers) }}</p>
        <p class="mt-3">
            <a
                href="{{ route(\App\Support\HrLookup::routeName($lookup, 'upload.template')) }}"
                class="font-medium text-[#0B318F] hover:underline"
                data-no-loader
                data-hr-lookup-template-download
            >
                Download Template
            </a>
        </p>
    </div>

    @if (session('upload_errors'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">
                @foreach (session('upload_errors') as $uploadError)
                    <li>{{ $uploadError }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <label class="form-label" for="hr-lookup-upload-file-{{ $lookup }}">Upload File <span class="text-red-500">*</span></label>
        <input id="hr-lookup-upload-file-{{ $lookup }}" type="file" name="upload_file" accept=".csv,.txt,.xlsx,.xls" class="form-input" required data-hr-lookup-upload-file>
        <p class="mt-1 text-xs text-gray-500">CSV or Excel file from the template.</p>
        @error('upload_file')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Upload',
        'cancelModalId' => 'hr-lookup-upload-'.$lookup,
    ])
</form>
