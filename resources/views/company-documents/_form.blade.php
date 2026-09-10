@php
    $fieldIdPrefix = $fieldIdPrefix ?? 'company-document';
@endphp

<div class="space-y-4">
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
        <select id="{{ $fieldIdPrefix }}_document_type" name="document_type" class="form-input w-full">
            @foreach ($documentTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('document_type', $form->document_type ?? 'memo') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="form-label" for="{{ $fieldIdPrefix }}_description">Description</label>
        <textarea id="{{ $fieldIdPrefix }}_description" name="description" rows="3" class="form-input w-full">{{ old('description', $form->description ?? '') }}</textarea>
        @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label" for="{{ $fieldIdPrefix }}_submit_label">Submit button label</label>
            <input id="{{ $fieldIdPrefix }}_submit_label" type="text" name="submit_label" value="{{ old('submit_label', $form->submit_label ?? 'Submit') }}" class="form-input w-full" maxlength="80">
        </div>
        <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="allow_multiple_submissions" value="0">
                <input type="checkbox" name="allow_multiple_submissions" value="1" class="rounded border-gray-300" @checked(old('allow_multiple_submissions', $form->allow_multiple_submissions ?? true))>
                Allow multiple submissions
            </label>
        </div>
    </div>

    <div>
        <label class="form-label" for="{{ $fieldIdPrefix }}_success_message">Success message</label>
        <textarea id="{{ $fieldIdPrefix }}_success_message" name="success_message" rows="2" class="form-input w-full">{{ old('success_message', $form->success_message ?? '') }}</textarea>
    </div>
</div>
