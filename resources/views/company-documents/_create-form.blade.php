<form method="POST" action="{{ route('company-documents.store') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="form_context" value="create-company-document">
    @include('company-documents._form', ['fieldIdPrefix' => 'create-company-document', 'form' => new \App\Models\CompanyDocumentForm(['document_type' => 'memo'])])
    @include('partials.modal-form-actions')
</form>
