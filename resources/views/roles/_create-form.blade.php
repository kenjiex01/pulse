<form method="POST" action="{{ route('roles.store') }}" class="space-y-5">
    @csrf
    <input type="hidden" name="form_context" value="create-role">
    @include('roles._form', ['fieldIdPrefix' => 'create-role-'])
    @include('roles._module-permissions', ['fieldIdPrefix' => 'create-role-'])
    @include('roles._role-members')
    @include('partials.modal-form-actions')
</form>
