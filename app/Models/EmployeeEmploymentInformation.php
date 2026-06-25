<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeEmploymentInformation extends Model
{
    use SoftDeletes;

    public const TYPE_FACULTY = 'faculty';

    public const TYPE_STAFF = 'staff';

    public const TYPE_ADMIN = 'admin';

    protected $table = 'tbl_employee_employment_information';

    protected $primaryKey = 'employment_info_id';

    protected $fillable = [
        'employee_id',
        'user_type',
        'position',
        'designation',
        'rank',
        'employment_type',
        'hire_date',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (EmployeeEmploymentInformation $info) {
            if ($info->isForceDeleting()) {
                return;
            }

            $info->salary?->delete();
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function salary(): HasOne
    {
        return $this->hasOne(EmployeeSalary::class, 'employment_info_id', 'employment_info_id');
    }

    public function getUserTypeLabelAttribute(): string
    {
        return match ($this->user_type) {
            self::TYPE_FACULTY => 'Faculty',
            self::TYPE_STAFF => 'Staff',
            self::TYPE_ADMIN => 'Admin',
            default => (string) $this->user_type,
        };
    }
}
