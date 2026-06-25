<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'modules' => ['nullable', 'array'],
            'modules.*.can_add' => ['nullable', 'boolean'],
            'modules.*.can_edit' => ['nullable', 'boolean'],
            'modules.*.can_update' => ['nullable', 'boolean'],
            'modules.*.can_delete' => ['nullable', 'boolean'],
            'modules.*.full_control' => ['nullable', 'boolean'],
            'sub_modules' => ['nullable', 'array'],
            'sub_modules.*.can_add' => ['nullable', 'boolean'],
            'sub_modules.*.can_edit' => ['nullable', 'boolean'],
            'sub_modules.*.can_update' => ['nullable', 'boolean'],
            'sub_modules.*.can_delete' => ['nullable', 'boolean'],
            'sub_modules.*.full_control' => ['nullable', 'boolean'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Role name is required.',
        ];
    }
}
