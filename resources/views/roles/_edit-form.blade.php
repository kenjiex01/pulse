<form method="POST" action="{{ route('roles.update', $role) }}" class="space-y-4">
    @csrf
    @method('PUT')
    <input type="hidden" name="edit_role_id" value="{{ $role->id }}">
    @include('roles._form', ['role' => $role, 'fieldIdPrefix' => 'edit-role-'.$role->id.'-'])
    @include('partials.modal-form-actions', ['submitLabel' => 'Update'])
</form>
