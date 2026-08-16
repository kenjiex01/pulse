<form
    method="POST"
    action="{{ route('employees.credentials.store', $employee) }}"
    enctype="multipart/form-data"
    class="space-y-4"
>
    @csrf
    <input type="hidden" name="form_context" value="create-employee-credential">

    @php
        $documentTypes = $documentTypes ?? \App\Models\DocumentType::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('type_name')
            ->get();
    @endphp

    <div>
        <label for="employee-credential-document-type" class="form-label">Document Type <span class="text-red-500">*</span></label>
        <select
            id="employee-credential-document-type"
            name="document_type_id"
            class="form-input"
            required
        >
            <option value="">Select document type</option>
            @foreach ($documentTypes as $documentType)
                <option
                    value="{{ $documentType->document_type_id }}"
                    @selected((string) old('document_type_id') === (string) $documentType->document_type_id)
                >
                    {{ $documentType->type_name }}{{ $documentType->is_required ? ' (Required)' : '' }}
                </option>
            @endforeach
        </select>
        @error('document_type_id')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="employee-credential-description" class="form-label">Description</label>
        <input
            id="employee-credential-description"
            type="text"
            name="description"
            class="form-input"
            value="{{ old('description') }}"
            maxlength="255"
            placeholder="Optional notes"
        >
        @error('description')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="employee-credential-attachment" class="form-label">File attachment <span class="text-red-500">*</span></label>
        <input
            id="employee-credential-attachment"
            type="file"
            name="attachment"
            class="form-input"
            required
            accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt,.csv,application/pdf,image/*"
        >
        <p class="mt-1 text-xs text-gray-500">
            PDF, image, Word, or Excel — max {{ (int) floor(config('uploads.max_file_kb', 15360) / 1024) }} MB.
        </p>
        @error('attachment')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Upload',
        'cancelModalId' => 'employee-credential-add-modal',
    ])
</form>
