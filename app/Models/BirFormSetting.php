<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BirFormSetting extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_bir_form_settings';

    protected $primaryKey = 'bir_form_setting_id';

    protected $fillable = [
        'company_name',
        'company_address',
        'company_tin',
        'company_rdo_code',
        'company_zip',
        'signatory_name',
        'signatory_title',
        'compensation_atc',
        'smw_rate_per_day',
        'smw_rate_per_month',
    ];

    protected function casts(): array
    {
        return [
            'smw_rate_per_day' => 'float',
            'smw_rate_per_month' => 'float',
        ];
    }

    public static function settings(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
