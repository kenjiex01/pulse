<div
    id="{{ $membersRootId }}-add-modal"
    class="modal-overlay modal-overlay-nested hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $membersRootId }}-add-modal-title"
>
    <div class="modal-backdrop" data-modal-close aria-hidden="true"></div>
    <div class="modal-panel modal-panel-lg">
        <div class="modal-header">
            <div>
                <h2 id="{{ $membersRootId }}-add-modal-title" class="text-lg font-bold text-[#0B318F]">Add Role Members</h2>
                <p class="mt-0.5 text-sm text-gray-500">Select one or more users not yet assigned to this role.</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close aria-label="Close">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body space-y-4">
            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700">
                    <input type="checkbox" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" data-role-members-select-all>
                    Select all
                </label>
                <p class="text-xs text-gray-500" data-role-members-selected-count>0 selected</p>
            </div>

            <div class="max-h-64 overflow-y-auto rounded-lg border border-gray-200" data-role-members-picker>
                <p class="px-4 py-8 text-center text-sm text-gray-500">All available users are already assigned to this role.</p>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                <button type="button" class="btn-secondary w-full sm:w-auto" data-modal-close>Cancel</button>
                <button type="button" class="btn-primary w-full sm:w-auto" data-role-members-add-confirm disabled>Add Selected</button>
            </div>
        </div>
    </div>
</div>
