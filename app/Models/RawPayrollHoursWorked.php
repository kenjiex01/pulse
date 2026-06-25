<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawPayrollHoursWorked extends Model
{
    protected $table = 'raw_payroll_hours_worked';

    protected $primaryKey = 'payroll_hours_worked_id';

    public $timestamps = false;

    protected $fillable = [
        'payroll_transaction_id',
        'employee_id',
        'day_type_id',
        'time_type_id',
        'hours',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function payrollTransaction(): BelongsTo
    {
        return $this->belongsTo(RawPayrollTransaction::class, 'payroll_transaction_id', 'payroll_transaction_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function dayType(): BelongsTo
    {
        return $this->belongsTo(DayType::class, 'day_type_id', 'day_type_id');
    }

    public function timeType(): BelongsTo
    {
        return $this->belongsTo(TimeType::class, 'time_type_id', 'time_type_id');
    }
}
