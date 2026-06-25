<div class="grid grid-cols-1 gap-2 rounded-md border border-gray-200 p-2 md:grid-cols-4" data-extended-row>
    <div>
        <label class="form-label">From</label>
        <input type="date" name="extended_profile[employment_history][{{ $index }}][from_date]" value="{{ old("extended_profile.employment_history.$index.from_date", $item['from_date'] ?? '') }}" class="form-input">
    </div>
    <div>
        <label class="form-label">To</label>
        <input type="date" name="extended_profile[employment_history][{{ $index }}][to_date]" value="{{ old("extended_profile.employment_history.$index.to_date", $item['to_date'] ?? '') }}" class="form-input">
    </div>
    <div>
        <label class="form-label">Company Name</label>
        <input type="text" name="extended_profile[employment_history][{{ $index }}][company_name]" value="{{ old("extended_profile.employment_history.$index.company_name", $item['company_name'] ?? '') }}" class="form-input" placeholder="Company Name">
    </div>
    <div>
        <label class="form-label">Position</label>
        <input type="text" name="extended_profile[employment_history][{{ $index }}][position]" value="{{ old("extended_profile.employment_history.$index.position", $item['position'] ?? '') }}" class="form-input" placeholder="Position">
    </div>
    <div>
        <label class="form-label">Salary</label>
        <input type="number" min="0" step="0.01" name="extended_profile[employment_history][{{ $index }}][salary]" value="{{ old("extended_profile.employment_history.$index.salary", $item['salary'] ?? '') }}" class="form-input" placeholder="Salary">
    </div>
    <div>
        <label class="form-label">Allowance</label>
        <input type="number" min="0" step="0.01" name="extended_profile[employment_history][{{ $index }}][allowance]" value="{{ old("extended_profile.employment_history.$index.allowance", $item['allowance'] ?? '') }}" class="form-input" placeholder="Allowance">
    </div>
    <div class="md:col-span-2">
        <label class="form-label">Reason for Leaving</label>
        <input type="text" name="extended_profile[employment_history][{{ $index }}][reason_for_leaving]" value="{{ old("extended_profile.employment_history.$index.reason_for_leaving", $item['reason_for_leaving'] ?? '') }}" class="form-input" placeholder="Reason for Leaving">
    </div>
    <div class="flex items-end justify-end md:col-span-4">
        <button type="button" class="btn-icon text-red-500 hover:bg-red-50 hover:text-red-600" data-extended-remove title="Remove">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
    </div>
</div>
