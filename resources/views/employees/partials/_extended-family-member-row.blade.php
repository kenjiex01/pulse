<div class="rounded-md border border-gray-200 bg-gray-50 p-3" data-extended-row>
    <input type="hidden" name="extended_profile[family_members][{{ $index }}][relationship_type]" value="{{ $type }}">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <div>
            <label class="form-label">Full Name</label>
            <input type="text" name="extended_profile[family_members][{{ $index }}][full_name]" value="{{ old("extended_profile.family_members.$index.full_name", $member['full_name'] ?? '') }}" class="form-input" placeholder="Enter full name">
        </div>
        <div>
            <label class="form-label">Occupation</label>
            <input type="text" name="extended_profile[family_members][{{ $index }}][occupation]" value="{{ old("extended_profile.family_members.$index.occupation", $member['occupation'] ?? '') }}" class="form-input" placeholder="Enter occupation">
        </div>
        <div>
            <label class="form-label">Age</label>
            <input type="number" min="0" name="extended_profile[family_members][{{ $index }}][age]" value="{{ old("extended_profile.family_members.$index.age", $member['age'] ?? '') }}" class="form-input" placeholder="Enter age">
        </div>
    </div>
    <div class="mt-2 flex justify-end">
        <button type="button" class="btn-icon text-red-500 hover:bg-red-50 hover:text-red-600" data-extended-remove title="Remove">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
    </div>
</div>
