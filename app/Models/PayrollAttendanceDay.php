<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollAttendanceDay extends Model
{
    use SoftDeletes;

    protected $table = 'trn_payroll_attendance_days';

    protected $primaryKey = 'payroll_attendance_day_id';

    protected $fillable = [
        'payroll_batch_detail_id',
        'employee_id',
        'work_date',
        'day_type',
        'shift_code_id',
        'time_in',
        'time_out',
        'basic',
        'excess_hours',
        'ot',
        'sot',
        'ndiff',
        'ndot',
        'ndsot',
        'late',
        'undertime',
        'break_late',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'basic' => 'decimal:2',
            'excess_hours' => 'decimal:2',
            'ot' => 'decimal:2',
            'sot' => 'decimal:2',
            'ndiff' => 'decimal:2',
            'ndot' => 'decimal:2',
            'ndsot' => 'decimal:2',
            'late' => 'decimal:2',
            'undertime' => 'decimal:2',
            'break_late' => 'decimal:2',
        ];
    }

    public function payrollBatchDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollBatchDetail::class, 'payroll_batch_detail_id', 'payroll_batch_detail_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class, 'shift_code_id', 'shift_code_id');
    }
}
