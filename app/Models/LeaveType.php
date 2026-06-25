<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_leave_types';

    protected $primaryKey = 'leave_type_id';

    protected $fillable = [
        'leave_type_code',
        'description',
        'computation_basis_id',
        'leave_apply_to_id',
        'late_undertime_leave_id',
        'is_valid_as_earned_leave',
        'is_valid_for_adjustment',
        'is_breakdown_in_report',
        'is_convertible_to_cash',
        'hours_non_taxable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'computation_basis_id' => 'integer',
            'leave_apply_to_id' => 'integer',
            'late_undertime_leave_id' => 'integer',
            'is_valid_as_earned_leave' => 'boolean',
            'is_valid_for_adjustment' => 'boolean',
            'is_breakdown_in_report' => 'boolean',
            'is_convertible_to_cash' => 'boolean',
            'hours_non_taxable' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function computationBasis()
    {
        return $this->belongsTo(ComputationBasis::class, 'computation_basis_id', 'computation_basis_id');
    }

    public function leaveApplyTo()
    {
        return $this->belongsTo(LeaveApplyTo::class, 'leave_apply_to_id', 'leave_apply_to_id');
    }

    public function lateUndertimeLeave()
    {
        return $this->belongsTo(LateUndertimeLeave::class, 'late_undertime_leave_id', 'late_undertime_leave_id');
    }
}
