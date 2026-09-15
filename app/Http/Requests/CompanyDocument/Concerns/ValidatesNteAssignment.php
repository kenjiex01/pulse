<?php

namespace App\Http\Requests\CompanyDocument\Concerns;

use App\Models\CompanyDocumentForm;
use Illuminate\Validation\Validator;

trait ValidatesNteAssignment
{
    protected function validateNteAssignment(Validator $validator, ?CompanyDocumentForm $currentForm = null): void
    {
        if (! $this->boolean('is_nte')) {
            return;
        }

        if ($currentForm !== null && ! $currentForm->is_active) {
            $validator->errors()->add(
                'is_nte',
                'Only an active memo can be set as NTE. Activate this template first.',
            );

            return;
        }

        $existing = CompanyDocumentForm::conflictingActiveNte($currentForm?->company_document_form_id);

        if ($existing !== null) {
            $validator->errors()->add(
                'is_nte',
                'Another active memo is already set as NTE ('.$existing->name.'). Uncheck Set as NTE on that template first.',
            );
        }
    }
}
