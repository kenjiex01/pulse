@php
    $primaryKey = $config['primary_key'];
    $isEdit = $record !== null;
    $action = $isEdit
        ? route(\App\Support\HrLookup::routeName($lookup, 'update'), $record->{$primaryKey})
        : route(\App\Support\HrLookup::routeName($lookup, 'store'));
    $penaltiesByFrequency = $isEdit ? $record->penaltiesByFrequency() : [];
    $categoryCode = $isEdit ? $record->code : old('code', '');
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
                <input type="checkbox" name="{{ $fieldName }}" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(filter_var(old($fieldName, $record?->{$fieldName} ?? ($field['default'] ?? true)), FILTER_VALIDATE_BOOLEAN))>
                <span class="text-sm font-medium text-gray-900">{{ $field['label'] }}</span>
            </label>
            @error($fieldName)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @elseif ($fieldType === 'textarea')
            <div>
                <label for="{{ $fieldName }}_{{ $formContext }}" class="form-label">{{ $field['label'] }}</label>
                <textarea id="{{ $fieldName }}_{{ $formContext }}" name="{{ $fieldName }}" rows="3" class="form-input min-h-[80px] py-2">{{ $value }}</textarea>
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
                    @if (isset($field['step'])) step="{{ $field['step'] }}" @endif
                >
                @error($fieldName)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @endif
    @endforeach

    <div class="space-y-3 border-t border-gray-100 pt-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Table of Penalties</h3>
            <p class="mt-1 text-xs text-gray-500">
                @if ($categoryCode !== '')
                    Set the penalty for each offense frequency under Category {{ $categoryCode }}. Leave blank if not applicable.
                @else
                    Set the penalty for each offense frequency. Leave blank if not applicable.
                @endif
            </p>
        </div>

        @forelse ($frequencies ?? [] as $frequency)
            @php
                $ordinal = $frequency->frequency_ordinal;
                $penaltyValue = old('penalties.'.$ordinal, $penaltiesByFrequency[$ordinal] ?? '');
            @endphp
            <div>
                <label for="penalties_{{ $ordinal }}_{{ $formContext }}" class="form-label">{{ $frequency->label }}</label>
                <input
                    id="penalties_{{ $ordinal }}_{{ $formContext }}"
                    name="penalties[{{ $ordinal }}]"
                    type="text"
                    value="{{ $penaltyValue }}"
                    class="form-input"
                    placeholder="Leave blank if not applicable"
                >
                @error('penalties.'.$ordinal)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @empty
            <p class="text-xs text-gray-500">No frequencies yet. Add a frequency row first, then set penalties per category.</p>
        @endforelse
    </div>

    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
        <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Save Changes' : 'Create' }}</button>
    </div>
</form>
