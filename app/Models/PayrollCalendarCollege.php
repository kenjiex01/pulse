<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCalendarCollege extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_payroll_calendar_colleges';

    protected $primaryKey = 'payroll_calendar_college_id';

    protected $fillable = [
        'payroll_calendar_id',
        'college_id',
    ];

    public function payrollCalendar(): BelongsTo
    {
        return $this->belongsTo(PayrollCalendar::class, 'payroll_calendar_id', 'payroll_calendar_id');
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'college_id', 'college_id');
    }
}
