<?php

namespace App\Http\Requests\CompanyDocument;

use App\Http\Requests\CompanyDocument\Concerns\ValidatesNteAssignment;
use App\Models\CompanyDocumentForm;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCompanyDocumentFormRequest extends FormRequest
{
    use ValidatesNteAssignment;
    public function authorize(): bool
    {
        $form = $this->route('companyDocumentForm');

        return $form instanceof CompanyDocumentForm
            && ($this->user()?->can('update', $form) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CompanyDocumentForm $form */
        $form = $this->route('companyDocumentForm');

        return [
            'name' => ['required', 'string', 'max:200'],
            'code' => [
                'required',
                'string',
                'max:80',
                ValidationRules::uniqueSoft('tbl_company_document_forms', 'code', $form->company_document_form_id, 'company_document_form_id'),
            ],
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
            /** @var CompanyDocumentForm $form */
            $form = $this->route('companyDocumentForm');

            $this->validateNteAssignment($validator, $form);
        });
    }
}
