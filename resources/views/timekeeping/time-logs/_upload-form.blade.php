@php
    use App\Support\TimeLogs as TimeLogsSupport;
    use App\Support\TimeLogsDtr;
@endphp

<form
    method="POST"
    action="{{ route(TimeLogsSupport::routeName('process')) }}"
    enctype="multipart/form-data"
    class="space-y-4"
    data-time-logs-upload-form
    @if ($requiresCampus ?? false) data-time-logs-dtr-form @endif
>
    @csrf
    <input type="hidden" name="tab" value="{{ $tab }}">

    @if ($requiresCampus ?? false)
        <div>
            <label class="form-label">Campus <span class="text-red-500">*</span></label>
            <select name="campus_id" class="form-input" required data-no-searchable-select data-time-logs-dtr-campus-select>
                <option value="">Select campus</option>
                @foreach ($dtrCampuses as $campus)
                    <option
                        value="{{ $campus->campus_id }}"
                        data-file-extension="{{ TimeLogsDtr::acceptedExtension($campus) }}"
                        data-campus-code="{{ $campus->campus_code }}"
                        @selected((string) old('campus_id') === (string) $campus->campus_id)
                    >
                        {{ $campus->campus_name }}
                    </option>
                @endforeach
            </select>
            @error('campus_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

    @unless ($requiresCampus ?? false)
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
    @endunless

    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600">
        <p class="font-medium text-gray-900">Before uploading</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            @if ($requiresCampus ?? false)
                <li>Select the campus that provided the DTR file.</li>
                <li>Upload the campus Excel file as provided (no conversion needed).</li>
                <li>Cainta Main and San Mateo use <strong>.xls</strong>; Sumulong uses <strong>.xlsx</strong>.</li>
                <li>Cainta Main: <strong>Timesheet Report</strong> export with <code>Employee: NAME (biometric ID)</code> blocks and In/Out columns.</li>
                <li>San Mateo workbooks may include multiple tabs; only <strong>Card Report</strong> sheets are imported.</li>
                <li>Sumulong uploads the campus <strong>DTR Report</strong> export — employee name plus ID number in parentheses.</li>
                <li>Sumulong matches employees by ID number in <strong>( )</strong>, then by name on that campus if needed.</li>
                <li>San Mateo Card Report IDs must match the employee&apos;s <strong>Biometric ID</strong> on that campus (Assignment tab).</li>
            @else
                <li>Select a format type, then download the template.</li>
                <li>Open the template in Excel, fill in your data, remove instruction rows.</li>
                <li>Save as <strong>Text (Tab delimited) (*.txt)</strong> or CSV.</li>
            @endif
        </ol>
        @unless ($requiresCampus ?? false)
            <p class="mt-3">
                <a href="#" class="text-[#0B318F] hover:underline hidden" data-time-logs-template-link target="_blank" rel="noopener">Download Template</a>
            </p>
        @endunless
    </div>

    <div>
        <label class="form-label">Upload File <span class="text-red-500">*</span></label>
        <input
            type="file"
            name="upload_file"
            accept="{{ ($requiresCampus ?? false) ? '.xls,.xlsx' : '.txt,.csv,text/plain,text/csv' }}"
            class="form-input"
            required
            @if ($requiresCampus ?? false) data-time-logs-dtr-file-input @endif
        >
        <p class="mt-1 text-xs text-gray-500" @if ($requiresCampus ?? false) data-time-logs-dtr-file-hint @endif>
            @if ($requiresCampus ?? false)
                Select a campus first. Cainta: .xls Timesheet Report. San Mateo: .xls Card Report tabs only. Sumulong: .xlsx DTR Report.
            @else
                Tab-delimited text (.txt) or CSV only — not Excel workbooks.
            @endif
        </p>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Upload',
        'cancelModalId' => 'time-logs-upload-modal',
    ])
</form>
