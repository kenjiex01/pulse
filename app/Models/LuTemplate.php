<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LuTemplate extends Model
{
    public $timestamps = false;

    protected $table = 'lu_template';

    protected $primaryKey = 'template_id';

    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'template',
    ];

    public function timekeepingTemplates(): HasMany
    {
        return $this->hasMany(TimekeepingTemplate::class, 'template_name', 'template_id');
    }
}
