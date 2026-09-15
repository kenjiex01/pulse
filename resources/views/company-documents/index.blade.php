@extends('layouts.app')

@section('title', 'Company Documents — '.config('app.name'))

@section('content')
    @php
        $openCreate = ($errors->any() && old('form_context') === 'create-company-document') || ($openCreate ?? false);
        $openEditId = old('edit_company_document_form_id', request('edit_form'));
        $openViewId = request('view_form');
    @endphp

    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Company Documents',
        'description' => 'Create memo templates. Nature of offense is optional — not all memos, including NTE, are tied to an offense.',
        'actionModalId' => auth()->user()->can('create', \App\Models\CompanyDocumentForm::class) ? 'company-document-create-modal' : null,
        'actionLabel' => 'New Memo Template',
        'actionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    ])

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('company-documents.index') }}" class="w-full sm:max-w-xs">
            <label for="company-documents-search" class="sr-only">Search templates</label>
            <input
                id="company-documents-search"
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="Search templates..."
                class="form-input w-full"
            >
        </form>
    </div>

    @if ($forms->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm font-medium text-gray-900">No memo templates yet</p>
            <p class="mt-1 text-xs text-gray-500">Create your first template to start collecting memos.</p>
            @can('create', \App\Models\CompanyDocumentForm::class)
                <button type="button" data-modal-open="company-document-create-modal" class="btn-primary mt-4">
                    New Memo Template
                </button>
            @endcan
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($forms as $form)
                @include('company-documents._card', ['form' => $form])
            @endforeach
        </div>
    @endif

    @can('create', \App\Models\CompanyDocumentForm::class)
        @include('partials.modal', [
            'id' => 'company-document-create-modal',
            'title' => 'New Memo Template',
            'description' => 'Nature of offense is optional. Tick Set as NTE if this template is the Notice to Explain.',
            'open' => $openCreate,
            'panelClass' => 'max-w-xl',
            'body' => view('company-documents._create-form', compact('documentTypes', 'icctOffenses'))->render(),
        ])
    @endcan

    @foreach ($forms as $form)
        @can('update', $form)
            @include('partials.modal', [
                'id' => 'company-document-edit-modal-'.$form->company_document_form_id,
                'title' => 'Edit Template',
                'description' => $form->name,
                'open' => (string) $openEditId === (string) $form->company_document_form_id,
                'panelClass' => 'max-w-lg',
                'body' => view('company-documents._edit-form', compact('form', 'documentTypes', 'icctOffenses'))->render(),
            ])
        @endcan

        @include('partials.modal', [
            'id' => 'company-document-view-modal-'.$form->company_document_form_id,
            'title' => 'Template Details',
            'description' => $form->code,
            'open' => (string) $openViewId === (string) $form->company_document_form_id,
            'panelClass' => 'max-w-lg',
            'body' => view('company-documents._show-content', compact('form'))->render(),
        ])

        @can('view', $form)
            @include('partials.modal', [
                'id' => 'company-document-preview-modal-'.$form->company_document_form_id,
                'title' => 'Document Preview',
                'description' => $form->name,
                'open' => false,
                'panelClass' => 'max-w-4xl modal-panel-document-preview',
                'bodyClass' => 'modal-body-document-preview',
                'body' => view('company-documents._preview-content', compact('form'))->render(),
            ])
        @endcan
    @endforeach
@endsection
