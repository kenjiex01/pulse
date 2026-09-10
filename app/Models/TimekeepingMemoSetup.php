<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimekeepingMemoSetup extends Model
{
    use SoftDeletes;

    public const TYPE_LATE = 'late';

    public const TYPE_UNDERTIME = 'undertime';

    public const TYPE_ABSENT = 'absent';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_LATE,
        self::TYPE_UNDERTIME,
        self::TYPE_ABSENT,
    ];

    protected $table = 'tbl_timekeeping_memo_setups';

    protected $primaryKey = 'timekeeping_memo_setup_id';

    protected $fillable = [
        'violation_type',
        'company_document_form_id',
        'email_subject',
        'email_body',
        'email_cc',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentForm::class, 'company_document_form_id', 'company_document_form_id');
    }

    public static function labelForType(string $type): string
    {
        return match ($type) {
            self::TYPE_LATE => 'Late',
            self::TYPE_UNDERTIME => 'Undertime',
            self::TYPE_ABSENT => 'Absent',
            default => ucfirst($type),
        };
    }

    /**
     * @return list<string>
     */
    public static function parseCcList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(static fn (string $email) => trim($email))
            ->filter(static fn (string $email) => $email !== '')
            ->values()
            ->all();
    }
}
