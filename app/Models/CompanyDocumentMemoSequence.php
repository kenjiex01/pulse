<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDocumentMemoSequence extends Model
{
    protected $table = 'tbl_company_document_memo_sequences';

    protected $primaryKey = 'company_document_memo_sequence_id';

    protected $fillable = [
        'year',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
