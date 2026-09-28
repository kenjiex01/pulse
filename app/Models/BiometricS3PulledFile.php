<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricS3PulledFile extends Model
{
    public const STATUS_IMPORTED = 'imported';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'tbl_biometric_s3_pulled_files';

    protected $primaryKey = 'biometric_s3_pulled_file_id';

    protected $fillable = [
        's3_key',
        'pulled_at',
        'pulled_by_user_id',
        'timekeeping_transaction_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pulled_at' => 'datetime',
        ];
    }

    public function pulledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pulled_by_user_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(RawTimekeepingTransaction::class, 'timekeeping_transaction_id', 'timekeeping_transaction_id');
    }
}
