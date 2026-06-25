<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class TimekeepingPolicyLeave extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_timekeeping_policy_leave';

    protected $primaryKey = 'timekeeping_policy_leave_id';

    protected $fillable = ['timekeeping_policy_id', 'leave_type_id', 'time_from', 'time_to', 'equivalent'];

    protected function casts(): array
    {
        return [
            'leave_type_id' => 'integer',
            'time_from' => 'decimal:4',
            'time_to' => 'decimal:4',
            'equivalent' => 'decimal:4',
        ];
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id', 'leave_type_id');
    }
}
