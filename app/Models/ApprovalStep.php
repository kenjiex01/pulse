<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalStep extends Model
{
    protected $table = 'tbl_approval_steps';

    protected $primaryKey = 'approval_step_id';

    protected $fillable = [
        'employee_id',
        'sys_user_id',
        'form_type',
        'step_no',
        'automatic_forwarding',
        'hours_before_forwarding',
    ];

    protected function casts(): array
    {
        return [
            'automatic_forwarding' => 'boolean',
            'hours_before_forwarding' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ApprovalStepMember::class, 'approval_step_id', 'approval_step_id');
    }
}
