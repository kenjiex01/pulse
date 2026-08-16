@php
    $credentials = $employee->relationLoaded('credentials')
        ? $employee->credentials
        : $employee->credentials()->get();
    $openCredentialModal = old('form_context') === 'create-employee-credential'
        || $errors->has('document_type_id')
        || $errors->has('description')
        || $errors->has('attachment');
@endphp

@foreach ($credentials as $credential)
    <form
        id="destroy-employee-credential-{{ $credential->employee_credential_id }}"
        method="POST"
        action="{{ route('employees.credentials.destroy', [$employee, $credential]) }}"
        class="hidden"
    >
        @csrf
        @method('DELETE')
    </form>
@endforeach

@include('partials.modal', [
    'id' => 'employee-credential-add-modal',
    'title' => 'Add Credential',
    'description' => 'Upload a credential file for this employee.',
    'panelClass' => 'max-w-lg',
    'open' => $openCredentialModal,
    'body' => view('employees.partials._credential-add-form', [
        'employee' => $employee,
        'documentTypes' => $documentTypes ?? null,
    ])->render(),
])

@include('partials.modal', [
    'id' => 'employee-credential-preview-modal',
    'title' => 'Credential Preview',
    'panelClass' => 'max-w-5xl',
    'open' => false,
    'body' => view('employees.partials._credential-preview-body')->render(),
])
