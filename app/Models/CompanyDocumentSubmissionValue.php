<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocumentSubmissionValue extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_company_document_submission_values';

    protected $primaryKey = 'value_id';

    protected $fillable = [
        'submission_id',
        'element_id',
        'field_key',
        'value_text',
        'value_json',
        'file_path',
        'original_filename',
        'mime_type',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentSubmission::class, 'submission_id', 'submission_id');
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentElement::class, 'element_id', 'element_id');
    }
}
