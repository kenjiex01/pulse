<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeCampusAssignment extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_employee_campus_assignments';

    protected $primaryKey = 'employee_campus_assignment_id';

    protected $fillable = [
        'employee_id',
        'campus_id',
        'biometric_id',
        'college',
        'department',
        'program',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'campus_id');
    }
}
