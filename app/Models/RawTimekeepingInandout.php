<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawTimekeepingInandout extends Model
{
    protected $table = 'raw_timekeeping_inandout';

    protected $primaryKey = 'timekeeping_inandout_id';

    public $timestamps = false;

    protected $fillable = [
        'timekeeping_transaction_id',
        'employee_id',
        'dt_datetime',
        'original_dt_datetime',
        'original_is_in',
        'is_edited',
        'edited_at',
        'edited_by_id',
        'is_in',
        'timekeeping_trantype',
        'reference_number',
    ];

    protected function casts(): array
    {
        return [
            'dt_datetime' => 'datetime',
            'original_dt_datetime' => 'datetime',
            'original_is_in' => 'boolean',
            'is_edited' => 'boolean',
            'edited_at' => 'datetime',
            'is_in' => 'boolean',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(RawTimekeepingTransaction::class, 'timekeeping_transaction_id', 'timekeeping_transaction_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_id', 'id');
    }
}
