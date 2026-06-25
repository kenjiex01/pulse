@php
    use App\Support\TimekeepingTemplate as TimekeepingTemplateSupport;

    $selectedType = (int) old('template_name', $record?->template_name ?? 0);
    $placeholders = TimekeepingTemplateSupport::placeholdersForType($selectedType ?: null);
    $contentMaxLength = config('timekeeping_templates.content_max_length', 1000);
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route(TimekeepingTemplateSupport::routeName('update'), $record->timekeeping_template_id) : route(TimekeepingTemplateSupport::routeName('store')) }}"
    class="space-y-4"
    data-timekeeping-template-form
    data-tkt-placeholder-map='@json(config('timekeeping_templates.placeholders'))'
>
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="edit_timekeeping_template_id" value="{{ $record->timekeeping_template_id }}">
    @endif
    <input type="hidden" name="form_context" value="{{ $formContext }}">

    <p class="text-sm text-gray-500">
        Please fill out all required fields. Click a placeholder to insert it into the content.
    </p>

    <div>
        <label for="template_name_{{ $formContext }}" class="form-label">Template for <span class="text-red-500">*</span></label>
        <select
            id="template_name_{{ $formContext }}"
            name="template_name"
            class="form-input max-w-md"
            data-tkt-template-type
            data-no-searchable-select
            required
        >
            <option value="">— Please select —</option>
            @foreach ($templateTypes as $typeId => $typeLabel)
                <option value="{{ $typeId }}" @selected($selectedType === (int) $typeId)>{{ $typeLabel }}</option>
            @endforeach
        </select>
        @error('template_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_180px]">
        <div>
            <label for="content_{{ $formContext }}" class="form-label">Content <span class="text-red-500">*</span></label>
            <textarea
                id="content_{{ $formContext }}"
                name="content"
                rows="8"
                maxlength="{{ $contentMaxLength }}"
                class="form-input resize-y"
                data-tkt-content
                required
            >{{ old('content', $record?->content) }}</textarea>
            @error('content')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <p class="form-label">Placeholders</p>
            <ul
                class="space-y-1 rounded-md border border-gray-200 bg-gray-50 p-2 text-xs text-[#0089c2]"
                data-tkt-placeholders
                @if ($selectedType === 0) hidden @endif
            >
                @foreach ($placeholders as $token)
                    <li>
                        <button
                            type="button"
                            class="w-full rounded px-1 py-0.5 text-left hover:bg-white hover:underline"
                            data-tkt-insert-placeholder="[{{ $token }}]"
                        >
                            [{{ $token }}]
                        </button>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-gray-500" data-tkt-placeholder-hint @if ($selectedType !== 0) hidden @endif>
                Select a template type to show available placeholders.
            </p>
        </div>
    </div>

    <div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="is_active" value="0">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                @checked(filter_var(old('is_active', $record?->is_active ?? true), FILTER_VALIDATE_BOOLEAN))
            >
            Is Active
        </label>
        @error('is_active')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-gray-500">Only one active template is allowed per notification type.</p>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => $isEdit ? 'Save Changes' : 'Create Template',
    ])
</form>
