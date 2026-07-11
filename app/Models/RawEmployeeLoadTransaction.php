<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawEmployeeLoadTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'raw_employee_load_transactions';

    protected $primaryKey = 'employee_load_transaction_id';

    protected $fillable = [
        'batch_no',
        'filename',
        'enrollment_period_id',
        'enrollment_period_label',
        'dt_from',
        'dt_to',
        'uploaded_by_id',
        'dt_uploaded',
    ];

    protected function casts(): array
    {
        return [
            'batch_no' => 'integer',
            'enrollment_period_id' => 'integer',
            'dt_from' => 'date',
            'dt_to' => 'date',
            'dt_uploaded' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (RawEmployeeLoadTransaction $transaction) {
            if ($transaction->isForceDeleting()) {
                $transaction->entries()->withTrashed()->forceDelete();

                return;
            }

            $transaction->entries()->delete();
        });
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RawEmployeeLoadEntry::class, 'employee_load_transaction_id', 'employee_load_transaction_id');
    }

    public function formattedBatchNo(): string
    {
        return str_pad((string) $this->batch_no, 4, '0', STR_PAD_LEFT);
    }

    public function dateRangeLabel(): string
    {
        if (! $this->dt_from || ! $this->dt_to) {
            return '—';
        }

        return $this->dt_from->format('M j, Y').' – '.$this->dt_to->format('M j, Y');
    }
}
