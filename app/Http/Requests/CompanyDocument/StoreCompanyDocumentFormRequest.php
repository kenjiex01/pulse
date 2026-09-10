<?php

namespace App\Http\Requests\CompanyDocument;

use App\Models\CompanyDocumentForm;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyDocumentFormRequest extends FormRequest
{
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
            'allow_multiple_submissions' => ['nullable', 'boolean'],
            'submit_label' => ['nullable', 'string', 'max:80'],
            'success_message' => ['nullable', 'string'],
        ];
    }
}
