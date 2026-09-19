<div class="space-y-4" data-payroll-report-options="memo">
    <h3 class="text-base font-semibold text-gray-900">Memo Options</h3>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="memo-date-from" class="form-label">Date From <span class="text-red-500">*</span></label>
            <input
                type="date"
                id="memo-date-from"
                name="date_from"
                class="form-input"
                value="{{ old('date_from') }}"
                required
            >
            @error('date_from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="memo-date-to" class="form-label">Date To <span class="text-red-500">*</span></label>
            <input
                type="date"
                id="memo-date-to"
                name="date_to"
                class="form-input"
                value="{{ old('date_to') }}"
                required
            >
            @error('date_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div data-report-document-multiselect>
        <label for="memo-document-search" class="form-label">Company Documents <span class="text-red-500">*</span></label>
        <input
            type="search"
            id="memo-document-search"
            class="form-input"
            placeholder="Search memo code or name..."
            autocomplete="off"
            data-report-document-multiselect-search
        >
        <p class="mt-1 text-xs text-gray-500">Select one or more memo templates to include in the send log.</p>

        <div class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
            <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700">
                <input
                    type="checkbox"
                    class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                    data-report-document-multiselect-select-all
                >
                Select all
            </label>
            <p class="text-xs text-gray-500" data-report-document-multiselect-count>0 selected</p>
        </div>

        <div class="mt-2 max-h-72 overflow-y-auto rounded-lg border border-gray-200 bg-white">
            @forelse ($memoForms as $memoForm)
                <label
                    class="flex cursor-pointer items-start gap-3 border-b border-gray-100 px-3 py-2 text-sm last:border-b-0 hover:bg-gray-50"
                    data-report-document-multiselect-item
                    data-report-document-search-text="{{ strtolower(trim(($memoForm->code ?? '').' '.$memoForm->name)) }}"
                >
                    <input
                        type="checkbox"
                        name="company_document_form_ids[]"
                        value="{{ $memoForm->company_document_form_id }}"
                        class="mt-0.5 rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                        data-report-document-multiselect-row
                        @checked(collect(old('company_document_form_ids', []))->contains($memoForm->company_document_form_id))
                    >
                    <span class="min-w-[7rem] font-mono text-xs text-gray-500">{{ $memoForm->code }}</span>
                    <span class="min-w-0 flex-1 text-gray-900">{{ $memoForm->name }}</span>
                </label>
            @empty
                <p class="px-3 py-4 text-sm text-gray-500">No active memo templates found.</p>
            @endforelse
        </div>
        @error('company_document_form_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('company_document_form_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div>
            <label for="memo-output-format" class="form-label">Output</label>
            <select id="memo-output-format" name="output_format" class="form-input" required>
                @foreach ($report->fileTypes as $fileType)
                    <option
                        value="{{ $fileType->code }}"
                        @selected(old('output_format', 'excel') === $fileType->code)
                    >
                        {{ $fileType->label }}
                    </option>
                @endforeach
            </select>
            @error('output_format')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <p class="text-xs text-gray-500">
        Lists employees who received the selected company document memos within the date range, including sends from Company Documents and Timekeeping.
    </p>
</div>
