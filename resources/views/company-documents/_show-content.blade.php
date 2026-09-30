<dl class="space-y-3 text-sm">
    <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Name</dt>
        <dd class="mt-1 text-gray-900">{{ $form->name }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Code</dt>
        <dd class="mt-1 font-mono text-gray-900">{{ $form->code }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Type</dt>
        <dd class="mt-1 text-gray-900">{{ \App\Models\CompanyDocumentForm::documentTypes()[$form->document_type] ?? $form->document_type }}</dd>
    </div>
    @if ($form->icctOffense)
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nature of offense</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $form->icctOffense->dropdownLabel() }}</dd>
        </div>
    @endif
    <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Set as NTE</dt>
        <dd class="mt-1 text-gray-900">{{ $form->is_nte ? 'Yes — this template is the Notice to Explain' : 'No' }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Web NTE response</dt>
        <dd class="mt-1 text-gray-900">
            @if ($form->expects_web_nte_response)
                Yes — {{ $form->nteResponseDays() }} day(s) on Skolaris web
            @else
                No
            @endif
        </dd>
    </div>
    <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</dt>
        <dd class="mt-1">{{ $form->is_active ? 'Active' : 'Inactive' }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Version</dt>
        <dd class="mt-1 text-gray-900">{{ $form->version }}</dd>
    </div>
    @if ($form->description)
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Description</dt>
            <dd class="mt-1 text-gray-700">{{ $form->description }}</dd>
        </div>
    @endif
</dl>

@include('company-documents._nte-cases', [
    'form' => $form,
    'nteCases' => $nteCases ?? collect(),
    'nteSyncConfigured' => $nteSyncConfigured ?? false,
])
