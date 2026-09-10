<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocumentSubmission extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_IN_APPROVAL = 'in_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'tbl_company_document_submissions';

    protected $primaryKey = 'submission_id';

    protected $fillable = [
        'company_document_form_id',
        'form_version',
        'form_snapshot_json',
        'submitted_by_user_id',
        'status',
        'current_step',
        'submitted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'form_snapshot_json' => 'array',
            'form_version' => 'integer',
            'current_step' => 'integer',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentForm::class, 'company_document_form_id', 'company_document_form_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CompanyDocumentSubmissionValue::class, 'submission_id', 'submission_id');
    }

    public function submissionApprovals(): HasMany
    {
        return $this->hasMany(CompanyDocumentSubmissionApproval::class, 'submission_id', 'submission_id')
            ->orderBy('step_number');
    }

    public function logSnapshot(): array
    {
        return $this->only([
            'submission_id',
            'company_document_form_id',
            'status',
            'current_step',
            'submitted_by_user_id',
        ]);
    }

    public function getRouteKeyName(): string
    {
        return 'submission_id';
    }
}
