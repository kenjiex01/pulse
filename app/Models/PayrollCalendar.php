<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollCalendar extends Model
{
    protected $table = 'tbl_payroll_calendar';

    protected $primaryKey = 'payroll_calendar_id';

    protected $fillable = [
        'pay_type_id',
        'pay_year',
        'pay_period',
        'dt_from',
        'dt_to',
        'calendar_month',
        'is_regular_period',
    ];

    protected function casts(): array
    {
        return [
            'pay_year' => 'integer',
            'pay_period' => 'integer',
            'calendar_month' => 'integer',
            'dt_from' => 'datetime',
            'dt_to' => 'datetime',
            'is_regular_period' => 'boolean',
        ];
    }

    public function payType(): BelongsTo
    {
        return $this->belongsTo(PayType::class, 'pay_type_id', 'pay_type_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollCalendarDeduction::class, 'payroll_calendar_id', 'payroll_calendar_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(PayrollCalendarLoan::class, 'payroll_calendar_id', 'payroll_calendar_id');
    }

    public function colleges(): HasMany
    {
        return $this->hasMany(PayrollCalendarCollege::class, 'payroll_calendar_id', 'payroll_calendar_id');
    }

    public function userTypes(): HasMany
    {
        return $this->hasMany(PayrollCalendarUserType::class, 'payroll_calendar_id', 'payroll_calendar_id');
    }

    public function formattedPayPeriod(): string
    {
        return str_pad((string) $this->pay_period, 3, '0', STR_PAD_LEFT);
    }
}
