<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WizardCampusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'campus_id' => ['required', Rule::exists('tbl_campuses', 'campus_id')->where('is_active', true)],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('employees.create', ['step' => 0]);
    }
}
