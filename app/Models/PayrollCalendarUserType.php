<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCalendarUserType extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_payroll_calendar_user_types';

    protected $primaryKey = 'payroll_calendar_user_type_id';

    protected $fillable = [
        'payroll_calendar_id',
        'user_type',
    ];

    public function payrollCalendar(): BelongsTo
    {
        return $this->belongsTo(PayrollCalendar::class, 'payroll_calendar_id', 'payroll_calendar_id');
    }
}
