<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawPayrollShiftCode extends Model
{
    protected $table = 'raw_payroll_shift_codes';

    protected $primaryKey = 'payroll_shift_code_id';

    public $timestamps = false;

    protected $fillable = [
        'payroll_transaction_id',
        'employee_id',
        'work_date',
        'shift_code_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
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

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class, 'shift_code_id', 'shift_code_id');
    }
}
