<?php

namespace App\Rules;

use App\Support\GovernmentIdNumbers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GovernmentIdNumber implements ValidationRule
{
    public function __construct(private readonly string $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! GovernmentIdNumbers::isValid(is_string($value) ? $value : null, $this->type)) {
            $fail(GovernmentIdNumbers::validationMessage($this->type));
        }
    }
}
