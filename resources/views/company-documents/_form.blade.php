@php
    $fieldIdPrefix = $fieldIdPrefix ?? 'company-document';
    $icctOffenses = $icctOffenses ?? collect();
    $selectedDocumentType = old('document_type', $form->document_type ?? 'memo');
@endphp

@php
    $isNteLetterTemplate = filter_var($form->is_nte ?? false, FILTER_VALIDATE_BOOLEAN);
@endphp

<div
    class="space-y-4"
    data-company-document-form
    data-company-document-is-nte-letter="{{ $isNteLetterTemplate ? '1' : '0' }}"
>
    <div>
        <label class="form-label" for="{{ $fieldIdPrefix }}_name">Template name <span class="text-red-500">*</span></label>
        <input id="{{ $fieldIdPrefix }}_name" type="text" name="name" value="{{ old('name', $form->name ?? '') }}" class="form-input w-full" required maxlength="200">
        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label" for="{{ $fieldIdPrefix }}_code">Code</label>
        <input id="{{ $fieldIdPrefix }}_code" type="text" name="code" value="{{ old('code', $form->code ?? '') }}" class="form-input w-full font-mono text-sm" maxlength="80" placeholder="Auto-generated if blank">
        @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label" for="{{ $fieldIdPrefix }}_document_type">Document type</label>
        <select id="{{ $fieldIdPrefix }}_document_type" name="document_type" class="form-input w-full" data-company-document-type>
            @foreach ($documentTypes as $value => $label)
                <option value="{{ $value }}" @selected($selectedDocumentType === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div
        data-company-document-memo-offense
        @class(['space-y-1', 'hidden' => $selectedDocumentType !== \App\Models\CompanyDocumentForm::TYPE_MEMO])
    >
        <div
            data-company-document-offense-field
            @class(['space-y-1', 'hidden' => $isNteLetterTemplate])
        >
            <label class="form-label" for="{{ $fieldIdPrefix }}_icct_offense_id">
                Nature of offense (memo type)
            </label>
            <select
                id="{{ $fieldIdPrefix }}_icct_offense_id"
                name="icct_offense_id"
                class="form-input w-full"
                data-company-document-memo-offense-select
            >
                <option value="">Optional — select if this memo is for a specific offense</option>
                @foreach ($icctOffenses as $offense)
                    <option
                        value="{{ $offense->icct_offense_id }}"
                        title="{{ $offense->dropdownLabel() }}"
                        @selected((string) old('icct_offense_id', $form->icct_offense_id ?? '') === (string) $offense->icct_offense_id)
                    >{{ $offense->compactDropdownLabel() }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500">Optional. Not all memos have a nature of offense. Leave blank for NTE and general memos.</p>
            @error('icct_offense_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-3 space-y-2">
            <div
                class="space-y-2 rounded-lg border border-sky-100 bg-sky-50/40 px-3 py-3"
                data-company-document-web-nte
            >
                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="hidden" name="expects_web_nte_response" value="0">
                    <input
                        id="{{ $fieldIdPrefix }}_expects_web_nte_response"
                        type="checkbox"
                        name="expects_web_nte_response"
                        value="1"
                        class="mt-0.5 rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                        data-company-document-expects-web-nte
                        @checked(filter_var(old('expects_web_nte_response', $form->expects_web_nte_response ?? false), FILTER_VALIDATE_BOOLEAN))
                    >
                    <span>
                        <span class="font-medium text-gray-900">Require employee NTE on Skolaris web</span>
                        <span class="mt-0.5 block text-xs font-normal text-gray-600">When this template is sent, track a response deadline and remind the employee to submit on Skolaris (no separate Word NTE attachment).</span>
                    </span>
                </label>
                @error('expects_web_nte_response')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

                <div data-company-document-web-nte-days @class(['hidden' => ! filter_var(old('expects_web_nte_response', $form->expects_web_nte_response ?? false), FILTER_VALIDATE_BOOLEAN)])>
                    <label class="form-label" for="{{ $fieldIdPrefix }}_nte_response_days">Days to respond</label>
                    <input
                        id="{{ $fieldIdPrefix }}_nte_response_days"
                        type="number"
                        name="nte_response_days"
                        min="3"
                        max="30"
                        value="{{ old('nte_response_days', $form->nte_response_days ?? 7) }}"
                        class="form-input w-24"
                    >
                    <p class="mt-1 text-xs text-gray-500">Calendar days from send date (3–30).</p>
                    @error('nte_response_days')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
    </div>

    <div>
        <label class="form-label" for="{{ $fieldIdPrefix }}_description">Description</label>
        <textarea id="{{ $fieldIdPrefix }}_description" name="description" rows="3" class="form-input w-full">{{ old('description', $form->description ?? '') }}</textarea>
        @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
