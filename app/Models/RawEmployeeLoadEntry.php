<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawEmployeeLoadEntry extends Model
{
    use SoftDeletes;

    protected $table = 'raw_employee_load_entries';

    protected $primaryKey = 'employee_load_entry_id';

    public function getRouteKeyName(): string
    {
        return 'employee_load_entry_id';
    }

    protected $fillable = [
        'employee_load_transaction_id',
        'employee_id',
        'skolaris_offering_id',
        'employee_number',
        'faculty_name',
        'college',
        'modality',
        'subject',
        'section',
        'load_date',
        'session_date',
        'class_schedule',
        'total_hours',
        'time_in',
        'time_out',
        'late_waived',
        'remarks',
        'comments',
        'verification_remarks',
    ];

    protected function casts(): array
    {
        return [
            'employee_load_transaction_id' => 'integer',
            'employee_id' => 'integer',
            'skolaris_offering_id' => 'integer',
            'session_date' => 'date',
            'total_hours' => 'decimal:2',
            'late_waived' => 'boolean',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(RawEmployeeLoadTransaction::class, 'employee_load_transaction_id', 'employee_load_transaction_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
