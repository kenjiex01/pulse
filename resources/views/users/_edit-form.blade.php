<form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4">
    @csrf
    @method('PUT')
    <input type="hidden" name="edit_user_id" value="{{ $user->id }}">
    @include('users._form', ['user' => $user, 'roles' => $roles, 'fieldIdPrefix' => 'edit-user-'.$user->id.'-'])
    @include('partials.modal-form-actions', ['submitLabel' => 'Update'])
</form>
