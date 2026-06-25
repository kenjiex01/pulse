<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftCode extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_shift_codes';

    protected $primaryKey = 'shift_code_id';

    protected $fillable = [
        'shift_code',
        'description',
        'time_in',
        'time_out',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ShiftCode $shiftCode) {
            if ($shiftCode->isForceDeleting()) {
                return;
            }

            $shiftCode->breaks()->delete();
        });
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(ShiftCodeBreak::class, 'shift_code_id', 'shift_code_id')
            ->orderBy('shift_code_break_no');
    }
}
