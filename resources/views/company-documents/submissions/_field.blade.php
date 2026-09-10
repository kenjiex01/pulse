@php
    use App\Support\CompanyDocumentTextStyle;

    $key = $element->field_key;
    $oldValue = old("values.$key");
    $elementSettings = $element->settings_json ?? [];
@endphp

<div class="space-y-1">
    @switch($element->type)
        @case('heading')
            <h2 class="text-xl font-bold text-gray-900">{!! app(\App\Services\CompanyDocumentMergeTagService::class)->renderInlineTagsForDesign($element->label) !!}</h2>
            @break
        @case('paragraph')
            <p
                class="cd-designer-text-block cd-designer-paragraph whitespace-pre-wrap break-words"
                style="{{ CompanyDocumentTextStyle::inlineStyle($elementSettings) }}"
            >{!! app(\App\Services\CompanyDocumentMergeTagService::class)->renderInlineTagsForDesign($element->label) !!}</p>
            @break
        @case('divider')
            <hr class="border-gray-200">
            @break
        @case('merge_tag')
            @php
                $tagKey = (string) (($element->settings_json ?? [])['tag_key'] ?? '');
                $tagValue = $tagKey !== '' ? app(\App\Services\CompanyDocumentMergeTagService::class)->resolve($tagKey) : '—';
            @endphp
            <p class="inline-flex items-center rounded-md border border-[#00A3E6]/25 bg-[#00A3E6]/10 px-2.5 py-1.5 text-sm font-medium text-[#0B318F]">{{ $tagValue }}</p>
            @break
        @case('long_text')
            <label for="field_{{ $key }}" class="form-label">
                {{ $element->label }}
                @if ($element->is_required)<span class="text-red-500">*</span>@endif
            </label>
            @php
                $defaultText = (string) (($element->settings_json ?? [])['default_text'] ?? '');
                $fieldValue = $oldValue !== null && $oldValue !== '' ? $oldValue : $defaultText;
            @endphp
            <textarea id="field_{{ $key }}" name="values[{{ $key }}]" rows="4" class="form-input w-full" @required($element->is_required)>{{ $fieldValue }}</textarea>
            @break
        @case('date')
            <label for="field_{{ $key }}" class="form-label">
                {{ $element->label }}
                @if ($element->is_required)<span class="text-red-500">*</span>@endif
            </label>
            <input id="field_{{ $key }}" type="date" name="values[{{ $key }}]" value="{{ $oldValue }}" class="form-input w-full" @required($element->is_required)>
            @break
        @case('signature')
            <label class="form-label">
                {{ $element->label }}
                @if ($element->is_required)<span class="text-red-500">*</span>@endif
            </label>
            @include('company-documents.submissions._signature-pad', [
                'inputName' => 'files['.$key.']',
                'required' => (bool) $element->is_required,
            ])
            @break
        @case('file_upload')
            <label for="field_{{ $key }}" class="form-label">
                {{ $element->label }}
                @if ($element->is_required)<span class="text-red-500">*</span>@endif
            </label>
            <input id="field_{{ $key }}" type="file" name="files[{{ $key }}]" class="form-input w-full" @required($element->is_required)>
            @break
        @default
            @php
                $defaultText = (string) (($element->settings_json ?? [])['default_text'] ?? '');
                $fieldValue = $oldValue !== null && $oldValue !== '' ? $oldValue : $defaultText;
            @endphp
            <label for="field_{{ $key }}" class="form-label">
                {{ $element->label }}
                @if ($element->is_required)<span class="text-red-500">*</span>@endif
            </label>
            <input id="field_{{ $key }}" type="text" name="values[{{ $key }}]" value="{{ $fieldValue }}" class="form-input w-full" @required($element->is_required)>
    @endswitch

    @if ($element->help_text)
        <p class="text-xs text-gray-500">{{ $element->help_text }}</p>
    @endif
    @error($key)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
