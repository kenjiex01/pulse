@php
    $primaryKey = $config['primary_key'];
@endphp

<form method="POST" action="{{ $isEdit ? route(\App\Support\PayrollMaintenance::routeName('update'), ['tab' => $tab, 'record' => $record->{$primaryKey}]) : route(\App\Support\PayrollMaintenance::routeName('store'), ['tab' => $tab]) }}" class="space-y-4 payroll-maintenance-form" data-payroll-tab="{{ $tab }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="form_context" value="{{ $formContext }}">
    @if ($isEdit)
        <input type="hidden" name="edit_record_id" value="{{ $record->{$primaryKey} }}">
    @endif

    @foreach ($config['fields'] as $field)
        @php
            $fieldName = $field['name'];
            $fieldType = $field['type'] ?? 'text';
            $value = old($fieldName, $record?->{$fieldName});
            $dependsOn = $field['depends_on'] ?? null;
            $dependsAttr = $dependsOn
                ? ' data-depends-on="'.e($dependsOn['field']).'" data-depends-value="'.e((string) $dependsOn['value']).'"'
                : '';
            $defaultChecked = array_key_exists('default', $field) ? $field['default'] : false;
        @endphp

        @if ($fieldType === 'checkbox')
            <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-3 payroll-maintenance-field" data-field="{{ $fieldName }}"{!! $dependsAttr !!}>
                <input type="hidden" name="{{ $fieldName }}" value="0">
                <input type="checkbox" name="{{ $fieldName }}" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(filter_var(old($fieldName, $record?->{$fieldName} ?? $defaultChecked), FILTER_VALIDATE_BOOLEAN))>
                <span class="text-sm font-medium text-gray-900">{{ $field['label'] }}</span>
            </label>
            @error($fieldName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @elseif ($fieldType === 'select')
            <div class="payroll-maintenance-field" data-field="{{ $fieldName }}"{!! $dependsAttr !!}>
                <label for="{{ $fieldName }}_{{ $formContext }}" class="form-label">{{ $field['label'] }}</label>
                <select id="{{ $fieldName }}_{{ $formContext }}" name="{{ $fieldName }}" class="form-input">
                    <option value="">Select {{ $field['label'] }}</option>
                    @foreach ($selectOptions[$field['source']] ?? [] as $optionValue => $optionLabel)
                        <option value="{{ $optionValue }}" @selected((string) old($fieldName, $record?->{$fieldName} ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
                    @endforeach
                </select>
                @error($fieldName)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @else
            <div class="payroll-maintenance-field" data-field="{{ $fieldName }}"{!! $dependsAttr !!}>
                <label for="{{ $fieldName }}_{{ $formContext }}" class="form-label">{{ $field['label'] }}</label>
                <input
                    id="{{ $fieldName }}_{{ $formContext }}"
                    name="{{ $fieldName }}"
                    type="{{ $fieldType === 'number' ? 'number' : 'text' }}"
                    value="{{ $value }}"
                    class="form-input"
                    @if ($fieldType === 'number') step="{{ str_contains($fieldName, 'amount') || str_contains($fieldName, 'hours') ? '0.01' : '1' }}" min="0" @endif
                    @if ($fieldName === 'sss_loan_type') maxlength="1" @endif
                >
                @error($fieldName)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @endif
    @endforeach

    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Save Changes' : 'Create' }}</button>
    </div>
</form>
