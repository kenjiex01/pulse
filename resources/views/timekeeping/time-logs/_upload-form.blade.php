@php
    use App\Support\TimeLogs as TimeLogsSupport;
@endphp

<form
    method="POST"
    action="{{ route(TimeLogsSupport::routeName('process')) }}"
    enctype="multipart/form-data"
    class="space-y-4"
    data-time-logs-upload-form
>
    @csrf

    <div>
        <label class="form-label">Format Type <span class="text-red-500">*</span></label>
        <select name="timecapture_format_id" class="form-input" required data-time-logs-format-select>
            <option value="">Select format type</option>
            @foreach ($formats as $format)
                <option
                    value="{{ $format->timecapture_format_id }}"
                    data-template-url="{{ route(TimeLogsSupport::routeName('template'), $format->timecapture_format_id) }}"
                    @selected((string) old('timecapture_format_id') === (string) $format->timecapture_format_id)
                >
                    {{ $format->device_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600">
        <p class="font-medium text-gray-900">Before uploading</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Select a format type, then download the template.</li>
            <li>Open the template in Excel, fill in your data, remove instruction rows.</li>
            <li>Save as <strong>Text (Tab delimited) (*.txt)</strong> or CSV.</li>
        </ol>
        <p class="mt-3">
            <a href="#" class="text-[#0B318F] hover:underline hidden" data-time-logs-template-link target="_blank" rel="noopener">Download Template</a>
        </p>
    </div>

    <div>
        <label class="form-label">Upload File <span class="text-red-500">*</span></label>
        <input type="file" name="upload_file" accept=".txt,.csv,text/plain,text/csv" class="form-input" required>
        <p class="mt-1 text-xs text-gray-500">Tab-delimited text (.txt) or CSV only — not Excel workbooks.</p>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Upload',
        'cancelModalId' => 'time-logs-upload-modal',
    ])
</form>
