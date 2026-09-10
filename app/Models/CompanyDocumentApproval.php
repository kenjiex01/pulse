<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocumentApproval extends Model
{
    use SoftDeletes;

    public const MODE_SINGLE = 'single';
    public const MODE_ANY_OF = 'any_of';
    public const MODE_ALL_OF = 'all_of';

    protected $table = 'tbl_company_document_approvals';

    protected $primaryKey = 'approval_id';

    protected $fillable = [
        'company_document_form_id',
        'step_number',
        'name',
        'mode',
        'optional',
        'sla_hours',
        'instructions',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'optional' => 'boolean',
            'sla_hours' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentForm::class, 'company_document_form_id', 'company_document_form_id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(CompanyDocumentApprovalAssignee::class, 'approval_id', 'approval_id');
    }

    /**
     * @return array<string, string>
     */
    public static function modes(): array
    {
        return [
            self::MODE_SINGLE => 'Single approver',
            self::MODE_ANY_OF => 'Any one approver',
            self::MODE_ALL_OF => 'All approvers',
        ];
    }
}
