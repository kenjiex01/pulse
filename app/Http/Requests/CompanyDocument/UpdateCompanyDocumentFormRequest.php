<?php

namespace App\Http\Requests\CompanyDocument;

use App\Models\CompanyDocumentForm;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyDocumentFormRequest extends FormRequest
{
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
            'allow_multiple_submissions' => ['nullable', 'boolean'],
            'submit_label' => ['nullable', 'string', 'max:80'],
            'success_message' => ['nullable', 'string'],
        ];
    }
}
