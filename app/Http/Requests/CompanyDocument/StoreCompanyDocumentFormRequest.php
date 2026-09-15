<?php

namespace App\Http\Requests\CompanyDocument;

use App\Http\Requests\CompanyDocument\Concerns\ValidatesNteAssignment;
use App\Models\CompanyDocumentForm;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompanyDocumentFormRequest extends FormRequest
{
    use ValidatesNteAssignment;
    public function authorize(): bool
    {
        return $this->user()?->can('create', CompanyDocumentForm::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:80', ValidationRules::uniqueSoft('tbl_company_document_forms', 'code')],
            'description' => ['nullable', 'string'],
            'document_type' => ['required', 'string', Rule::in(array_keys(CompanyDocumentForm::documentTypes()))],
            'icct_offense_id' => [
                'nullable',
                'integer',
                Rule::exists('lu_icct_offenses', 'icct_offense_id'),
            ],
            'requires_nte' => ['nullable', 'boolean'],
            'is_nte' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('icct_offense_id') === '' || $this->boolean('is_nte')) {
            $this->merge(['icct_offense_id' => null]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateNteAssignment($validator);
        });
    }
}
