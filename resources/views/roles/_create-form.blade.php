<form method="POST" action="{{ route('roles.store') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="form_context" value="create-role">
    @include('roles._form', ['fieldIdPrefix' => 'create-role-'])
    @include('partials.modal-form-actions')
</form>
