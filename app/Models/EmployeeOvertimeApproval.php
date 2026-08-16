<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeOvertimeApproval extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_employee_overtime_approvals';

    protected $primaryKey = 'employee_overtime_approval_id';

    protected $fillable = [
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
