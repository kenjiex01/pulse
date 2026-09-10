<form method="POST" action="{{ route('company-documents.update', $form) }}" class="space-y-4">
    @csrf
    @method('PUT')
    <input type="hidden" name="edit_company_document_form_id" value="{{ $form->company_document_form_id }}">
    @include('company-documents._form', ['fieldIdPrefix' => 'edit-company-document-'.$form->company_document_form_id, 'form' => $form])
    @include('partials.modal-form-actions')
</form>
