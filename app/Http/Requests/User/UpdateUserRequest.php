<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', Rule::exists('roles', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Kailangan ang pangalan.',
            'email.required' => 'Kailangan ang email.',
            'email.unique' => 'Ginagamit na ang email na ito.',
            'password.min' => 'Dapat hindi bababa sa 8 character ang password.',
            'password.confirmed' => 'Hindi magkatugma ang password.',
            'role_id.required' => 'Pumili ng role.',
            'role_id.exists' => 'Hindi wasto ang napiling role.',
        ];
    }
}
