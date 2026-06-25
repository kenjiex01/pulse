<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStepMember extends Model
{
    protected $table = 'tbl_approval_steps_members';

    protected $primaryKey = 'approval_step_member_id';

    protected $fillable = [
        'approval_step_id',
        'user_id',
        'allow_batch_approve',
        'allow_view_attendance',
    ];

    protected function casts(): array
    {
        return [
            'allow_batch_approve' => 'boolean',
            'allow_view_attendance' => 'boolean',
        ];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'approval_step_id', 'approval_step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
