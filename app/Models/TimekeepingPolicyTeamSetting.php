<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimekeepingPolicyTeamSetting extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_timekeeping_policy_team_settings';

    protected $primaryKey = 'timekeeping_policy_team_setting_id';

    protected $fillable = [
        'limit',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'limit' => 'integer',
        ];
    }
}
