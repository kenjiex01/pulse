<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GovtTable extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_govt_tables';

    protected $primaryKey = 'govt_table_id';

    protected $fillable = [
        'govt_table_code',
        'description',
        'order_by',
    ];
}
