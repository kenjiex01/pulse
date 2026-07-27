<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollLeave extends Model
{
    use SoftDeletes;

    protected $table = 'trn_payroll_leaves';

    protected $primaryKey = 'payroll_leave_id';

    protected $fillable = [
        'payroll_batch_detail_id',
        'leave_type_id',
        'dt_from',
        'dt_to',
        'leave_hours',
        'cost',
        'reason',
        'is_editable',
        'is_deletable',
        'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'dt_from' => 'datetime',
            'dt_to' => 'datetime',
            'leave_hours' => 'decimal:2',
            'cost' => 'decimal:2',
            'is_editable' => 'boolean',
            'is_deletable' => 'boolean',
            'is_manual' => 'boolean',
        ];
    }

    public function payrollBatchDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollBatchDetail::class, 'payroll_batch_detail_id', 'payroll_batch_detail_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id', 'leave_type_id');
    }
}
