<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeType extends Model
{
    public const TIME_CLASS_REGULAR = 1;

    public const TIME_CLASS_NIGHT_DIFF = 2;

    public $timestamps = false;

    protected $table = 'tbl_time_types';

    protected $primaryKey = 'time_type_id';

    protected $fillable = [
        'time_type_code',
        'description',
        'time_class_id',
    ];

    protected function casts(): array
    {
        return [
            'time_class_id' => 'integer',
        ];
    }
}
