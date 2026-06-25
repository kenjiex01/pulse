<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSettingOther extends Model
{
    protected $table = 'tbl_payroll_setting_others';

    protected $primaryKey = 'payroll_setting_other_id';

    protected $fillable = [
        'is_deduction_loan_priority_enabled',
        'last_batch_no',
    ];

    protected function casts(): array
    {
        return [
            'is_deduction_loan_priority_enabled' => 'boolean',
            'last_batch_no' => 'integer',
        ];
    }

    public static function settings(): self
    {
        return static::query()->firstOrCreate([], [
            'is_deduction_loan_priority_enabled' => false,
            'last_batch_no' => 0,
        ]);
    }
}
