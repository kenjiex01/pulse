<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocumentNteCase extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'tbl_company_document_nte_cases';

    protected $primaryKey = 'company_document_nte_case_id';

    protected $fillable = [
        'company_document_send_log_id',
        'company_document_form_id',
        'employee_id',
        'sent_at',
        'due_at',
        'status',
        'skolaris_request_id',
        'responded_at',
        'response_snapshot_json',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'due_at' => 'datetime',
            'responded_at' => 'datetime',
            'response_snapshot_json' => 'array',
            'skolaris_request_id' => 'integer',
        ];
    }

    public function sendLog(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentSendLog::class, 'company_document_send_log_id', 'company_document_send_log_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentForm::class, 'company_document_form_id', 'company_document_form_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_OVERDUE], true);
    }
}
