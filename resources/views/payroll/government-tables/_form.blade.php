@php
    $primaryKey = $config['primary_key'];
@endphp

<form method="POST" action="{{ $isEdit ? route('payroll.government-tables.update', ['tab' => $tab, 'record' => $record->{$primaryKey}]) : route('payroll.government-tables.store', ['tab' => $tab]) }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="edit_record_id" value="{{ $record->{$primaryKey} }}">
    @endif
    <input type="hidden" name="form_context" value="{{ $formContext }}">

    @foreach ($config['fields'] as $field)
        @php
            $fieldName = $field['name'];
            $fieldType = $field['type'] ?? 'text';
            $value = old($fieldName, $record?->{$fieldName});
        @endphp

        @if ($fieldType === 'checkbox')
            <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-3">
                <input type="hidden" name="{{ $fieldName }}" value="0">
                <input type="checkbox" name="{{ $fieldName }}" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(filter_var(old($fieldName, $record?->{$fieldName}), FILTER_VALIDATE_BOOLEAN))>
                <span class="text-sm font-medium text-gray-900">{{ $field['label'] }}</span>
            </label>
            @error($fieldName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @else
            <div>
                <label for="{{ $fieldName }}_{{ $formContext }}" class="form-label">{{ $field['label'] }}</label>
                <input
                    id="{{ $fieldName }}_{{ $formContext }}"
                    name="{{ $fieldName }}"
                    type="{{ $fieldType === 'number' ? 'number' : 'text' }}"
                    value="{{ $value }}"
                    class="form-input {{ $fieldName === 'withholding_tax_class_code' ? 'uppercase' : '' }}"
                    @if ($fieldType === 'number') step="0.01" min="0" @endif
                    @if ($fieldName === 'withholding_tax_class_code') maxlength="4" @endif
                    @if ($fieldName === 'description') maxlength="45" @endif
                    @if ($fieldName === 'number_of_dependents') max="99" step="1" @endif
                >
                @error($fieldName)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @endif
    @endforeach

    <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">Save</button>
    </div>
</form>
