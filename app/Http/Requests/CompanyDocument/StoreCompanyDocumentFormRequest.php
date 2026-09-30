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
            'icct_offense_id' => [
                'nullable',
                'integer',
                Rule::exists('lu_icct_offenses', 'icct_offense_id'),
            ],
            'expects_web_nte_response' => ['nullable', 'boolean'],
            'nte_response_days' => ['nullable', 'integer', 'min:3', 'max:30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('icct_offense_id') === '') {
            $this->merge(['icct_offense_id' => null]);
        }
    }
}
