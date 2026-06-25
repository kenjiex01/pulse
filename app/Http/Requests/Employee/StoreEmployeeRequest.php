<?php

namespace App\Http\Requests\Employee;

use App\Http\Requests\Employee\Concerns\EmployeeFormRules;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    use EmployeeFormRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) ?? false;
    }

    public function rules(): array
    {
        return $this->employeeFieldRules();
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
        if ($key !== null) {
            return data_get($this->validatedEmployeeData(), $key, $default);
        }

        return $this->validatedEmployeeData();
    }

}
