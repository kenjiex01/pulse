<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeCode extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_time_codes';

    protected $primaryKey = 'time_code_id';

    protected $fillable = [
        'time_code',
        'description',
        'is_billable',
        'pass_out',
    ];

    protected function casts(): array
    {
        return [
            'is_billable' => 'boolean',
            'pass_out' => 'boolean',
        ];
    }
}
