<form method="POST" action="{{ $isEdit ? route('payroll.government-tables.wtax2023-annual.update', $record->govt_table_wtax_annual_2023_id) : route('payroll.government-tables.wtax2023-annual.store') }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="edit_record_id" value="{{ $record->govt_table_wtax_annual_2023_id }}">
    @endif
    <input type="hidden" name="form_context" value="{{ $formContext }}">

    <div>
        <label for="income_from_{{ $formContext }}" class="form-label">Income From</label>
        <input id="income_from_{{ $formContext }}" name="income_from" type="number" step="0.01" min="0" value="{{ old('income_from', $record?->income_from !== null ? \App\Support\GovernmentTables::formatWtaxGridValue($record->income_from) : '') }}" class="form-input" required>
        @error('income_from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="income_to_{{ $formContext }}" class="form-label">Income To</label>
        <input id="income_to_{{ $formContext }}" name="income_to" type="number" step="0.01" min="0" value="{{ old('income_to', $record?->income_to !== null ? \App\Support\GovernmentTables::formatWtaxGridValue($record->income_to) : '') }}" class="form-input" required>
        @error('income_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="amount_due_{{ $formContext }}" class="form-label">Tax Amount Due</label>
        <input id="amount_due_{{ $formContext }}" name="amount_due" type="number" step="0.01" min="0" value="{{ old('amount_due', $record?->amount_due !== null ? \App\Support\GovernmentTables::formatWtaxGridValue($record->amount_due) : '') }}" class="form-input" required>
        @error('amount_due')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="percentage_due_{{ $formContext }}" class="form-label">Tax Percentage Due</label>
        <input id="percentage_due_{{ $formContext }}" name="percentage_due" type="number" step="0.01" min="0" value="{{ old('percentage_due', $record?->percentage_due !== null ? \App\Support\GovernmentTables::formatWtaxGridValue($record->percentage_due) : '') }}" class="form-input" required>
        @error('percentage_due')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">Save</button>
    </div>
</form>
