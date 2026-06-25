@props([
    'name',
    'label',
    'options' => [],
    'optionGroups' => [],
    'selected' => [],
    'required' => false,
    'hint' => null,
    'size' => 8,
    'disabled' => false,
])

@php
    $selectedValues = collect($selected)->map(fn ($value) => (string) $value)->all();
    $useGroups = ! empty($optionGroups);
    $optionGroupByValue = [];

    if ($useGroups) {
        foreach ($optionGroups as $groupLabel => $groupOptions) {
            foreach ($groupOptions as $value => $optionLabel) {
                $optionGroupByValue[(string) $value] = $groupLabel;
            }
        }
    }
@endphp

<div data-dual-list-select data-dl-input-name="{{ $name }}" @if ($useGroups) data-dl-grouped @endif @class(['opacity-60 pointer-events-none' => $disabled])>
    <label class="form-label">
        {{ $label }}
        <span class="text-red-500{{ $required ? '' : ' hidden' }}" data-dl-required-marker>*</span>
    </label>
    @if ($hint)
        <p class="mb-2 text-xs text-gray-500" data-dl-hint>{{ $hint }}</p>
    @endif
    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:items-center">
        <div class="min-w-0">
            <p class="mb-1 text-xs font-medium text-gray-600">Available</p>
            <div class="dual-list-select-panel">
                <select
                    multiple
                    size="{{ $size }}"
                    class="dual-list-select"
                    data-dl-available
                    data-no-searchable-select
                    @disabled($disabled)
                >
                @if ($useGroups)
                    @foreach ($optionGroups as $groupLabel => $groupOptions)
                        <optgroup label="{{ $groupLabel }}">
                            @foreach ($groupOptions as $value => $optionLabel)
                                @if (! in_array((string) $value, $selectedValues, true))
                                    <option value="{{ $value }}" data-dl-group="{{ $groupLabel }}">{{ $optionLabel }}</option>
                                @endif
                            @endforeach
                        </optgroup>
                    @endforeach
                @else
                    @foreach ($options as $value => $optionLabel)
                        @if (! in_array((string) $value, $selectedValues, true))
                            <option value="{{ $value }}">{{ $optionLabel }}</option>
                        @endif
                    @endforeach
                @endif
                </select>
            </div>
        </div>
        <div class="flex flex-row gap-2 md:flex-col">
            <button type="button" class="btn-secondary text-xs" data-dl-add title="Add selected items" @disabled($disabled)>&gt;&gt;</button>
            <button type="button" class="btn-secondary text-xs" data-dl-remove title="Remove selected items" @disabled($disabled)>&lt;&lt;</button>
        </div>
        <div class="min-w-0">
            <p class="mb-1 text-xs font-medium text-gray-600">Selected</p>
            <div class="dual-list-select-panel">
                <select
                    multiple
                    size="{{ $size }}"
                    class="dual-list-select"
                    data-dl-selected
                    data-no-searchable-select
                    @disabled($disabled)
                >
                @foreach ($options as $value => $optionLabel)
                    @if (in_array((string) $value, $selectedValues, true))
                        <option
                            value="{{ $value }}"
                            @if ($useGroups && isset($optionGroupByValue[(string) $value])) data-dl-group="{{ $optionGroupByValue[(string) $value] }}" @endif
                            selected
                        >{{ $optionLabel }}</option>
                    @endif
                @endforeach
                </select>
            </div>
        </div>
    </div>
    <div data-dl-hidden-inputs>
        @foreach ($selectedValues as $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
    </div>
</div>
