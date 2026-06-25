<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCalendarLoan extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_payroll_calendar_loans';

    protected $primaryKey = 'payroll_calendar_loan_id';

    protected $fillable = [
        'payroll_calendar_id',
        'loan_type_id',
    ];

    public function payrollCalendar(): BelongsTo
    {
        return $this->belongsTo(PayrollCalendar::class, 'payroll_calendar_id', 'payroll_calendar_id');
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class, 'loan_type_id', 'loan_type_id');
    }
}
