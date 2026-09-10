@php
    $previewUrl = route('company-documents.preview-html', $form);
@endphp

<div class="company-document-preview-html" data-company-document-preview-html>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-5 py-4 sm:px-6">
            <h3 class="text-center text-lg font-semibold text-gray-900">{{ $form->name }}</h3>
            @if ($form->description)
                <p class="mt-1 whitespace-pre-wrap text-center text-sm text-gray-600">{{ $form->description }}</p>
            @endif
            <p class="mt-3 text-center text-xs font-medium text-[#0B318F]">
                Legal size (8.5 × 14 in) — same layout as the memo PDF
            </p>
        </div>
        <div class="bg-gray-100 p-3 sm:p-4">
            <iframe
                src="{{ $previewUrl }}"
                title="Memo template preview"
                class="block w-full rounded-lg border border-gray-200 bg-gray-200"
                style="height: min(85vh, 1600px); min-height: 900px;"
            ></iframe>
        </div>
    </div>
</div>
