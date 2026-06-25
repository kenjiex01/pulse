<?php

namespace App\Http\Requests\Employee;

use App\Http\Requests\Employee\Concerns\EmployeeFormRules;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class WizardDetailsRequest extends FormRequest
{
    use EmployeeFormRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) ?? false;
    }

    public function rules(): array
    {
        $rules = $this->employeeFieldRules();
        unset($rules['campus'], $rules['campus_id']);

        return $rules;
    }

    public function messages(): array
    {
        return $this->employeeValidationMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareEmployeeValidation();

        if (blank($this->input('employee_number'))) {
            $this->merge([
                'employee_number' => Employee::generateEmployeeNumber(),
            ]);
        }
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = array_merge($this->validatedEmployeeData(), [
            'employment_informations' => $this->employmentInformations(),
            'employee_salaries' => $this->employeeSalaries(),
        ]);

        if ($key !== null) {
            return data_get($data, $key, $default);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return route('employees.create', ['step' => 1]);
    }
}
