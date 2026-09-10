<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocumentSubmissionApproval extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'tbl_company_document_submission_approvals';

    protected $primaryKey = 'submission_approval_id';

    protected $fillable = [
        'submission_id',
        'approval_id',
        'step_number',
        'step_name',
        'mode',
        'optional',
        'assignee_user_id',
        'status',
        'comment',
        'signature_path',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'optional' => 'boolean',
            'acted_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentSubmission::class, 'submission_id', 'submission_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }
}
