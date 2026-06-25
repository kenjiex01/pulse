<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawPayrollResignedEmployee extends Model
{
    protected $table = 'raw_payroll_resigned_employees';

    protected $primaryKey = 'payroll_resigned_employee_id';

    public $timestamps = false;

    protected $fillable = [
        'payroll_transaction_id',
        'employee_id',
        'dt_resigned',
    ];

    protected function casts(): array
    {
        return [
            'dt_resigned' => 'date',
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
