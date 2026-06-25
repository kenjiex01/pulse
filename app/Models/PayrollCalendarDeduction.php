<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCalendarDeduction extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_payroll_calendar_deductions';

    protected $primaryKey = 'payroll_calendar_deduction_id';

    protected $fillable = [
        'payroll_calendar_id',
        'deduction_type_id',
    ];

    public function payrollCalendar(): BelongsTo
    {
        return $this->belongsTo(PayrollCalendar::class, 'payroll_calendar_id', 'payroll_calendar_id');
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id', 'deduction_type_id');
    }
}
