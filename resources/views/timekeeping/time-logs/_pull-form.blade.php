@php
    use App\Support\TimeLogs;
@endphp

<div
    class="space-y-4"
    data-teaching-load-pull-root
    data-tl-pull-start-url="{{ route(TimeLogs::routeName('pull.start')) }}"
    data-tl-pull-step-url="{{ route(TimeLogs::routeName('pull.step')) }}"
    data-tl-reload-url="{{ route(TimeLogs::routeName('tab'), ['tab' => TimeLogs::TEACHING_LOADS_TAB]) }}"
>
    @if ($skolarisListError ?? null)
        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            Cannot verify Skolaris employee numbers. Check <code class="text-xs">SKOLARIS_PULSE_API_KEY</code> or JWT credentials in your environment settings.
            <span class="block mt-1 text-xs">{{ $skolarisListError }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Date From <span class="text-red-500">*</span></label>
            <input type="date" name="date_from" class="form-input" required data-tl-date-from>
        </div>
        <div>
            <label class="form-label">Date To <span class="text-red-500">*</span></label>
            <input type="date" name="date_to" class="form-input" required data-tl-date-to>
        </div>
    </div>

    <div>
        <label for="tl-pull-employee-search" class="form-label">Search Employees</label>
        <input
            type="search"
            id="tl-pull-employee-search"
            value="{{ $pullSearch ?? '' }}"
            placeholder="Employee no. or name..."
            class="form-input"
            data-tl-employee-search
        >
        <p class="mt-1 text-xs text-gray-500">
            People360 faculty with a matching employee number in Skolaris. Multi-select who to pull for the date range above.
        </p>
    </div>

    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700">
            <input type="checkbox" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" data-tl-select-all>
            Select all shown
        </label>
        <p class="text-xs text-gray-500" data-tl-selected-count>0 selected</p>
    </div>

    <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200" data-tl-employee-picker>
        @forelse ($pullEmployees ?? [] as $employee)
            <label
                class="flex cursor-pointer items-center gap-3 border-b border-gray-100 px-3 py-2 text-sm last:border-b-0 hover:bg-gray-50"
                data-tl-employee-item
                data-tl-search-text="{{ strtolower(trim(($employee->employee_number ?? '').' '.$employee->full_name)) }}"
            >
                <input
                    type="checkbox"
                    value="{{ $employee->employee_id }}"
                    class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                    data-tl-employee-row
                >
                <span class="min-w-[7rem] font-medium text-gray-900">{{ $employee->employee_number }}</span>
                <span class="text-gray-600">{{ $employee->full_name }}</span>
            </label>
        @empty
            <p class="px-4 py-8 text-center text-sm text-gray-500">
                No eligible faculty found. Employees must be faculty in People360 with a matching employee number in Skolaris.
            </p>
        @endforelse
    </div>

    @if (($pullEmployees ?? collect())->count() >= 200)
        <p class="text-xs text-gray-500">Showing first 200 results. Use search to narrow the list.</p>
    @endif

    <div class="hidden space-y-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-3" data-tl-progress-panel>
        <div class="flex items-center justify-between text-sm font-medium text-[#0B318F]">
            <span>Pulling teaching loads from Skolaris…</span>
            <span data-tl-progress-label>0 / 0</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-white">
            <div class="h-full rounded-full bg-[#00A3E6] transition-all duration-300" style="width: 0%" data-tl-progress-bar></div>
        </div>
        <p class="text-xs text-gray-600" data-tl-progress-detail></p>
    </div>

    <p class="hidden text-sm text-red-600" data-tl-pull-error></p>

    <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
        <button type="button" class="btn-secondary w-full sm:w-auto" data-modal-close data-tl-cancel-btn>Cancel</button>
        <button type="button" class="btn-primary w-full sm:w-auto" data-tl-start-pull @disabled($skolarisListError ?? false)>Start Pull</button>
    </div>
</div>
