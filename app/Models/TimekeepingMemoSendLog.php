<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimekeepingMemoSendLog extends Model
{
    protected $table = 'tbl_timekeeping_memo_send_logs';

    protected $primaryKey = 'timekeeping_memo_send_log_id';

    protected $fillable = [
        'employee_id',
        'violation_type',
        'work_date',
        'company_document_form_id',
        'submission_id',
        'sent_by_user_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentForm::class, 'company_document_form_id', 'company_document_form_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentSubmission::class, 'submission_id', 'submission_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id', 'id');
    }
}
