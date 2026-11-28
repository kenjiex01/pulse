<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeRequest extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_employee_requests';

    protected $primaryKey = 'employee_request_id';

    protected $fillable = [
        'skolaris_request_id',
        'request_number',
        'request_type_code',
        'request_type_name',
        'status_code',
        'prepared_by_employee_number',
        'prepared_by_name',
        'department',
        'campus_name',
        'request_date',
        'approved_at',
        'source_updated_at',
        'approvers',
        'form_payload',
        'items',
        'employee_id',
        'applied_at',
        'apply_summary',
        'pulled_at',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'approved_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'approvers' => 'array',
            'form_payload' => 'array',
            'items' => 'array',
            'applied_at' => 'datetime',
            'pulled_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function payrollInclusions(): HasMany
    {
        return $this->hasMany(EmployeeRequestPayrollInclusion::class, 'employee_request_id', 'employee_request_id');
    }
}
