<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocumentApprovalAssignee extends Model
{
    use SoftDeletes;

    public const TYPE_USER = 'user';
    public const TYPE_ROLE = 'role';

    protected $table = 'tbl_company_document_approval_assignees';

    protected $primaryKey = 'assignee_id';

    protected $fillable = [
        'approval_id',
        'assignee_type',
        'user_id',
        'role_id',
    ];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentApproval::class, 'approval_id', 'approval_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
