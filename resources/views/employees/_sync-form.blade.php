<div
    class="space-y-4"
    data-employee-skolaris-sync
    data-pending-url="{{ route('employees.sync.pending') }}"
    data-preview-url="{{ route('employees.sync.preview') }}"
    data-apply-url="{{ route('employees.sync.apply') }}"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm text-gray-600">
                Profiles with pending field updates from ISKOLARIS. Matched by People360 employee ID. Salary, credentials, and loans are not overwritten.
            </p>
            <p class="mt-1 hidden text-xs text-red-600" data-employee-sync-error></p>
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <button type="button" class="btn-secondary w-full sm:w-auto" data-employee-sync-refresh>
                Refresh list
            </button>
            <button type="button" class="btn-primary w-full sm:w-auto" data-employee-sync-all disabled>
                Approve all
            </button>
        </div>
    </div>

    <div>
        <label for="employee-sync-search" class="form-label">Search</label>
        <input
            type="search"
            id="employee-sync-search"
            placeholder="Employee no. or name..."
            class="form-input"
            data-employee-sync-search
        >
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700">
            <input type="checkbox" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" data-employee-sync-select-all>
            Select all shown
        </label>
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-xs text-gray-500" data-employee-sync-selected-count>0 selected</p>
            <button type="button" class="btn-secondary !px-3 !py-1.5 text-sm" data-employee-sync-multiple disabled>
                Approve selected
            </button>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="table-skolaris min-w-full text-sm">
            <thead>
                <tr>
                    <th class="w-10 px-3 py-2"></th>
                    <th class="px-3 py-2 text-left">Employee No.</th>
                    <th class="px-3 py-2 text-left">Name</th>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-left">Changes</th>
                    <th class="px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody data-employee-sync-rows>
                <tr data-employee-sync-empty>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                        Loading profiles from ISKOLARIS…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <p class="hidden text-sm text-green-700" data-employee-sync-success></p>

    <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
        <button type="button" class="btn-secondary w-full sm:w-auto" data-modal-close>Close</button>
    </div>
</div>
