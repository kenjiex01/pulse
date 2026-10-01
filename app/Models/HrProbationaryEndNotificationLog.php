<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrProbationaryEndNotificationLog extends Model
{
    public const RECIPIENT_EMPLOYEE = 'employee';

    public const RECIPIENT_HR = 'hr';

    protected $table = 'tbl_hr_probationary_end_notification_logs';

    protected $primaryKey = 'hr_probationary_end_notification_log_id';

    protected $fillable = [
        'employee_id',
        'probationary_end_date',
        'days_before',
        'recipient_type',
        'hr_email_to',
        'email_subject',
        'email_body',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'probationary_end_date' => 'date',
            'days_before' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public static function wasSent(
        int $employeeId,
        string $probationaryEndDate,
        int $daysBefore,
        string $recipientType,
    ): bool {
        return static::query()
            ->where('employee_id', $employeeId)
            ->whereDate('probationary_end_date', $probationaryEndDate)
            ->where('days_before', $daysBefore)
            ->where('recipient_type', $recipientType)
            ->exists();
    }
}
