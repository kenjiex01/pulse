<?php

namespace App\Support;

use App\Models\DeductionType;

final class PayrollAllowanceDeduction
{
    public static function deductionTypeId(): ?int
    {
        return DeductionType::query()
            ->whereIn('deduction_type_code', ['ALWD', 'OTHR'])
            ->orderByRaw("CASE deduction_type_code WHEN 'ALWD' THEN 0 ELSE 1 END")
            ->value('deduction_type_id');
    }
}
