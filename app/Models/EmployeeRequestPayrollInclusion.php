<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeRequestPayrollInclusion extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_employee_request_payroll_inclusions';

    protected $primaryKey = 'employee_request_payroll_inclusion_id';

    protected $fillable = [
        'employee_request_id',
        'employee_id',
        'effect_key',
        'effect_type',
        'effect_date',
        'payroll_batch_id',
        'payroll_batch_detail_id',
        'hours',
        'amount',
        'included_at',
    ];

    protected function casts(): array
    {
        return [
            'effect_date' => 'date',
            'hours' => 'decimal:4',
            'amount' => 'decimal:2',
            'included_at' => 'datetime',
        ];
    }

    public function employeeRequest(): BelongsTo
    {
        return $this->belongsTo(EmployeeRequest::class, 'employee_request_id', 'employee_request_id');
    }

    public function payrollBatch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'payroll_batch_id', 'payroll_batch_id');
    }
}
