<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftCodeBreak extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_shift_code_breaks';

    protected $primaryKey = 'shift_code_break_id';

    protected $fillable = [
        'shift_code_id',
        'shift_code_break_no',
        'break_out',
        'break_in',
        'shift_code_break_minute',
        'shift_code_is_paid_break',
    ];

    protected function casts(): array
    {
        return [
            'shift_code_break_no' => 'integer',
            'shift_code_break_minute' => 'integer',
            'shift_code_is_paid_break' => 'boolean',
        ];
    }

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class, 'shift_code_id', 'shift_code_id');
    }
}
