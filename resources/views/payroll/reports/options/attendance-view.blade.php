@php
    $selectedEmployeeIds = collect(old('employee_ids', []))->map(fn ($id) => (int) $id);
@endphp

<div class="space-y-4" data-payroll-report-options="attendance-view">
    <h3 class="text-base font-semibold text-gray-900">Attendance View Options</h3>
    <p class="text-sm text-gray-600">
        Choose a date range and the employees to include. PDF matches the Employee Profile Attendance View table.
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <label for="attendance-view-date-from" class="form-label">Date From <span class="text-red-500">*</span></label>
            <input
                type="date"
                id="attendance-view-date-from"
                name="date_from"
                class="form-input"
                required
                value="{{ old('date_from') }}"
            >
            @error('date_from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="attendance-view-date-to" class="form-label">Date To <span class="text-red-500">*</span></label>
            <input
                type="date"
                id="attendance-view-date-to"
                name="date_to"
                class="form-input"
                required
                value="{{ old('date_to') }}"
            >
            @error('date_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="attendance-view-output-format" class="form-label">Output</label>
            <select id="attendance-view-output-format" name="output_format" class="form-input" required>
                @foreach ($report->fileTypes as $fileType)
                    <option
                        value="{{ $fileType->code }}"
                        @selected(old('output_format', 'pdf') === $fileType->code)
                    >
                        {{ $fileType->label }}
                    </option>
                @endforeach
            </select>
            @error('output_format')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div data-employee-multiselect>
        <label for="attendance-view-employee-search" class="form-label">Employees <span class="text-red-500">*</span></label>
        <input
            type="search"
            id="attendance-view-employee-search"
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
                >
                Select all shown
            </label>
            <p class="text-xs text-gray-500" data-employee-multiselect-count>0 selected</p>
        </div>

        <div class="mt-2 max-h-72 overflow-y-auto rounded-lg border border-gray-200 bg-white">
            @forelse ($employees ?? [] as $employee)
                <label
                    class="flex cursor-pointer items-center gap-3 border-b border-gray-100 px-3 py-2 text-sm last:border-b-0 hover:bg-gray-50"
                    data-employee-multiselect-item
                    data-employee-search-text="{{ strtolower(trim(($employee->employee_number ?? '').' '.$employee->full_name)) }}"
                >
                    <input
                        type="checkbox"
                        name="employee_ids[]"
                        value="{{ $employee->employee_id }}"
                        class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                        data-employee-multiselect-row
                        @checked($selectedEmployeeIds->contains((int) $employee->employee_id))
                    >
                    <span class="min-w-[7rem] font-medium text-gray-900">{{ $employee->employee_number }}</span>
                    <span class="text-gray-600">{{ $employee->full_name }}</span>
                </label>
            @empty
                <p class="px-4 py-8 text-center text-sm text-gray-500">No employees found.</p>
            @endforelse
        </div>
        @error('employee_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('employee_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <p class="text-xs text-gray-500">
        Date range is limited to 92 days. OT, late, and undertime fill only on days covered by a processed payroll batch.
    </p>
</div>
