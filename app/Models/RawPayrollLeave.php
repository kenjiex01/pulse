<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawPayrollLeave extends Model
{
    protected $table = 'raw_payroll_leaves';

    protected $primaryKey = 'payroll_leave_id';

    public $timestamps = false;

    protected $fillable = [
        'payroll_transaction_id',
        'employee_id',
        'leave_type_id',
        'dt_from',
        'dt_to',
        'leave_hours',
        'applies_to_leave_type_id',
        'applied_hours',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'dt_from' => 'datetime',
            'dt_to' => 'datetime',
            'leave_hours' => 'decimal:2',
            'applied_hours' => 'decimal:2',
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

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id', 'leave_type_id');
    }

    public function appliesToLeaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'applies_to_leave_type_id', 'leave_type_id');
    }
}
