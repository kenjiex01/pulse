<?php

namespace App\Http\Requests\Role;

use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name') && ! $this->filled('slug')) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', ValidationRules::uniqueSoft('roles', 'slug')],
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
            'slug.required' => 'Slug is required.',
            'slug.unique' => 'This slug is already in use.',
            'slug.alpha_dash' => 'Slug may only contain letters, numbers, dashes, and underscores.',
        ];
    }
}
