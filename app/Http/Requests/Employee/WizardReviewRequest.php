<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WizardReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', Rule::exists('roles', 'id')],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('employees.create', ['step' => 2]);
    }
}
