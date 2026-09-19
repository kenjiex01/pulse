@php
    $formId = (int) $form->company_document_form_id;
    $fieldPrefix = 'company-document-send-'.$formId;
    $selectedEmployeeIds = collect(old('employee_ids', []))->map(fn ($id) => (int) $id);
    $sendSummaryByEmployeeId = ($sendSummary ?? collect())->keyBy(fn ($row) => (int) ($row->employee?->employee_id ?? 0));
@endphp

<form
    method="POST"
    action="{{ route('company-documents.send', $form) }}"
    class="space-y-4"
    data-company-document-send-form
    data-no-loader
    data-send-one-url="{{ route('company-documents.send-one', $form) }}"
    data-send-batch-complete-url="{{ route('company-documents.send-batch-complete', $form) }}"
    data-send-index-url="{{ route('company-documents.index', request()->only('search')) }}"
    data-document-name="{{ $form->name }}"
>
    @csrf

    <p class="text-sm text-gray-600">
        Select employees to receive <span class="font-medium text-gray-900">{{ $form->name }}</span> by email. Each employee gets a PDF copy of the document.
    </p>

    <div data-employee-multiselect data-company-document-send-picker>
        <label for="{{ $fieldPrefix }}-search" class="form-label">Employees <span class="text-red-500">*</span></label>
        <input
            type="search"
            id="{{ $fieldPrefix }}-search"
            class="form-input"
            placeholder="Search employee no. or name..."
            autocomplete="off"
            data-employee-multiselect-search
        >
        <p class="mt-1 text-xs text-gray-500">Search filters the list. Checked employees stay selected even when hidden.</p>

        <div class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
            <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700">
                <input
                    type="checkbox"
                    class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                    data-employee-multiselect-select-all
                    data-company-document-send-select-all
                >
                Select all
            </label>
            <p class="text-xs text-gray-500" data-employee-multiselect-count>0 selected</p>
        </div>

        <div class="mt-2 max-h-72 overflow-y-auto rounded-lg border border-gray-200 bg-white">
            @forelse ($sendEmployees ?? [] as $employee)
                @php
                    $employeeSummary = $sendSummaryByEmployeeId->get((int) $employee->employee_id);
                    $sendCount = (int) ($employeeSummary->send_count ?? 0);
                @endphp
                <label
                    class="flex cursor-pointer items-start gap-3 border-b border-gray-100 px-3 py-2 text-sm last:border-b-0 hover:bg-gray-50"
                    data-employee-multiselect-item
                    data-employee-search-text="{{ strtolower(trim(($employee->employee_number ?? '').' '.$employee->full_name)) }}"
                >
                    <input
                        type="checkbox"
                        name="employee_ids[]"
                        value="{{ $employee->employee_id }}"
                        class="mt-0.5 rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                        data-employee-multiselect-row
                        @checked($selectedEmployeeIds->contains((int) $employee->employee_id))
                    >
                    <span class="min-w-[7rem] font-medium text-gray-900">{{ $employee->employee_number }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-gray-600">{{ $employee->full_name }}</span>
                        @if ($sendCount > 0)
                            <span class="mt-0.5 block text-[11px] text-amber-700">
                                Sent {{ number_format($sendCount) }}×
                                @if ($employeeSummary->last_sent_at)
                                    · last {{ $employeeSummary->last_sent_at->format('M j, Y g:i A') }}
                                @endif
                            </span>
                        @endif
                    </span>
                </label>
            @empty
                <p class="px-4 py-8 text-center text-sm text-gray-500">No active employees found.</p>
            @endforelse
        </div>
        @error('employee_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('employee_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Send',
    ])
</form>

@include('company-documents._send-history', [
    'sendSummary' => $sendSummary ?? collect(),
])
