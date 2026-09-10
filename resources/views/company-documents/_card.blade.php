<div class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-[#00A3E6]/40 hover:shadow-md">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <h3 class="truncate font-semibold text-gray-900">{{ $form->name }}</h3>
                @if ($form->is_active)
                    <span class="badge-success shrink-0">Active</span>
                @else
                    <span class="badge-muted shrink-0">Inactive</span>
                @endif
            </div>
            <p class="mt-0.5 truncate font-mono text-[11px] text-gray-400">{{ $form->code }}</p>
            @if ($form->description)
                <p class="mt-2 line-clamp-2 text-xs text-gray-600">{{ $form->description }}</p>
            @endif
        </div>
    </div>

    <ul class="mt-4 space-y-1.5 text-xs text-gray-600">
        <li class="flex items-center gap-2">
            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
            {{ number_format($form->elements_count) }} elements
        </li>
        <li class="flex items-center gap-2">
            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
            {{ \App\Models\CompanyDocumentForm::documentTypes()[$form->document_type] ?? ucfirst($form->document_type) }}
        </li>
        <li class="flex items-center gap-2">
            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Standalone (accessible directly)
        </li>
    </ul>

    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4">
        @can('design', $form)
            <a href="{{ route('company-documents.designer', $form) }}" class="btn-primary !px-3 !py-1.5 text-xs" data-no-loader>
                <svg class="mr-1 inline h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Design
            </a>
        @endcan

        @can('view', $form)
            <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" title="Preview document" data-modal-open="company-document-preview-modal-{{ $form->company_document_form_id }}">
                <svg class="mr-1 inline h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Preview
            </button>
        @endcan

        @can('update', $form)
            <form method="POST" action="{{ route('company-documents.toggle', $form) }}" class="inline">
                @csrf
                <button type="submit" class="btn-icon" title="{{ $form->is_active ? 'Deactivate' : 'Activate' }}">
                    @if ($form->is_active)
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    @else
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </button>
            </form>

            <button type="button" class="btn-icon" title="View" data-modal-open="company-document-view-modal-{{ $form->company_document_form_id }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>

            <button type="button" class="btn-icon" title="Edit" data-modal-open="company-document-edit-modal-{{ $form->company_document_form_id }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
        @endcan

        @can('create', \App\Models\CompanyDocumentForm::class)
            <form method="POST" action="{{ route('company-documents.duplicate', $form) }}" class="inline">
                @csrf
                <button type="submit" class="btn-icon" title="Duplicate">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
            </form>
        @endcan

        @can('delete', $form)
            <form method="POST" action="{{ route('company-documents.destroy', $form) }}" class="inline" onsubmit="return confirm('Delete this template?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-icon text-red-500 hover:bg-red-50" title="Delete">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </form>
        @endcan
    </div>
</div>
