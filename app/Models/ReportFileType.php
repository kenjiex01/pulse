<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ReportFileType extends Model
{
    public const CODE_HTML = 'html';

    public const CODE_EXCEL = 'excel';

    public $timestamps = false;

    protected $table = 'lu_report_file_types';

    protected $primaryKey = 'report_file_type_id';

    protected $fillable = [
        'code',
        'label',
        'extension',
        'content_type',
    ];

    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(
            Report::class,
            'tbl_report_file_type_options',
            'report_file_type_id',
            'report_id',
            'report_file_type_id',
            'report_id',
        );
    }
}
