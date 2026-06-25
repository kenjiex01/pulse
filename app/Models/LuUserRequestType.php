<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LuUserRequestType extends Model
{
    /** Form types hidden from Employee Profile → Approval Settings dropdown. */
    public const EXCLUDED_FROM_EMPLOYEE_PROFILE = [
        4,  // Work Schedule
        6,  // Cost Centers
        9,  // HR Forms
        10, // Transfer of Approval Rights
        11, // TOIL Credit
        13, // Transfer of Team
    ];

    public $timestamps = false;

    protected $table = 'lu_user_request_types';

    protected $primaryKey = 'user_request_type_id';

    protected $fillable = [
        'user_request_type',
        'filename',
        'is_employee',
        'is_user',
    ];

    protected function casts(): array
    {
        return [
            'is_employee' => 'boolean',
            'is_user' => 'boolean',
        ];
    }

    public function scopeForEmployeeProfileSettings(Builder $query): Builder
    {
        return $query
            ->where('is_employee', true)
            ->whereNotIn('user_request_type_id', self::EXCLUDED_FROM_EMPLOYEE_PROFILE)
            ->orderBy('user_request_type');
    }
}
