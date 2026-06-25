<?php

namespace App\Http\Requests\Employee;

use App\Http\Requests\Employee\Concerns\EmployeeFormRules;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    use EmployeeFormRules;

    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee && $this->user()?->can('update', $employee);
    }

    public function rules(): array
    {
        return $this->employeeFieldRules($this->route('employee'));
    }

    public function messages(): array
    {
        return $this->employeeValidationMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareEmployeeValidation();

        if ($this->filled('campus_id')) {
            $campus = \App\Models\Campus::query()->find($this->input('campus_id'));

            if ($campus) {
                $this->merge(['campus' => $campus->campus_code]);
            }
        }
    }

    public function validated($key = null, $default = null): mixed
    {
        if ($key !== null) {
            return data_get($this->validatedEmployeeData(), $key, $default);
        }

        return $this->validatedEmployeeData();
    }

    protected function getRedirectUrl(): string
    {
        $employee = $this->route('employee');
        $tab = $this->resolveEmployeeFormErrorTab($this->input('active_tab'));

        if ($employee instanceof Employee && filled($tab)) {
            return route('employees.edit', ['employee' => $employee, 'tab' => $tab]);
        }

        return parent::getRedirectUrl();
    }
}
