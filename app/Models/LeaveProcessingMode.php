<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveProcessingMode extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_leave_processing_modes';

    protected $primaryKey = 'leave_processing_mode_id';

    protected $fillable = ['mode_label'];
}
