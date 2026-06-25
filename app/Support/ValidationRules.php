<?php

namespace App\Support;

use Illuminate\Validation\Rules\Unique;

class ValidationRules
{
    public static function uniqueSoft(
        string $table,
        string $column = 'id',
        mixed $ignoreId = null,
        string $ignoreColumn = 'id',
    ): Unique {
        $rule = \Illuminate\Validation\Rule::unique($table, $column)->whereNull('deleted_at');

        if ($ignoreId !== null) {
            $rule->ignore($ignoreId, $ignoreColumn);
        }

        return $rule;
    }
}
