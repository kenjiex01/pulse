@php
    $primaryKey = $config['primary_key'];
    $isEdit = $record !== null;
    $action = $isEdit
        ? route(\App\Support\HrLookup::routeName($lookup, 'update'), $record->{$primaryKey})
        : route(\App\Support\HrLookup::routeName($lookup, 'store'));
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4">
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
        @endphp

        @if ($fieldType === 'checkbox')
            <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-3">
                <input type="hidden" name="{{ $fieldName }}" value="0">
                <input type="checkbox" name="{{ $fieldName }}" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(filter_var(old($fieldName, $record?->{$fieldName} ?? true), FILTER_VALIDATE_BOOLEAN))>
                <span class="text-sm font-medium text-gray-900">{{ $field['label'] }}</span>
            </label>
            @error($fieldName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @elseif ($fieldType === 'textarea')
            <div>
                <label for="{{ $fieldName }}_{{ $formContext }}" class="form-label">{{ $field['label'] }}</label>
                <textarea id="{{ $fieldName }}_{{ $formContext }}" name="{{ $fieldName }}" rows="3" class="form-input min-h-[80px] py-2">{{ $value }}</textarea>
                @error($fieldName)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @elseif ($fieldType === 'select')
            <div>
                <label for="{{ $fieldName }}_{{ $formContext }}" class="form-label">{{ $field['label'] }}</label>
                <select id="{{ $fieldName }}_{{ $formContext }}" name="{{ $fieldName }}" class="form-input">
                    <option value="">Select {{ $field['label'] }}</option>
                    @if (! empty($field['options']))
                        @foreach ($field['options'] as $optionValue => $optionLabel)
                            <option value="{{ $optionValue }}" @selected((string) old($fieldName, $record?->{$fieldName} ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
                        @endforeach
                    @else
                        @foreach ($selectOptions[$field['source']] ?? [] as $optionValue => $optionLabel)
                            <option value="{{ $optionValue }}" @selected((string) old($fieldName, $record?->{$fieldName} ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
                        @endforeach
                    @endif
                </select>
                @error($fieldName)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @else
            <div>
                <label for="{{ $fieldName }}_{{ $formContext }}" class="form-label">{{ $field['label'] }}</label>
                <input
                    id="{{ $fieldName }}_{{ $formContext }}"
                    name="{{ $fieldName }}"
                    type="{{ $fieldType }}"
                    value="{{ $value }}"
                    class="form-input"
                    @if ($fieldType === 'number') min="0" @endif
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
