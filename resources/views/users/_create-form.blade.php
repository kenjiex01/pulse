<form method="POST" action="{{ route('users.store') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="form_context" value="create-user">
    @include('users._form', ['roles' => $roles, 'fieldIdPrefix' => 'create-user-'])
    @include('partials.modal-form-actions')
</form>
