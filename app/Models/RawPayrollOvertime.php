<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawPayrollOvertime extends Model
{
    protected $table = 'raw_payroll_overtimes';

    protected $primaryKey = 'payroll_overtime_id';

    public $timestamps = false;

    protected $fillable = [
        'payroll_transaction_id',
        'employee_id',
        'work_date',
        'ot_start',
        'ot_end',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'ot_start' => 'datetime',
            'ot_end' => 'datetime',
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
}
