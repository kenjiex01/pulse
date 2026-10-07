<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeachingLoadPullBatchEmployee extends Model
{
    use SoftDeletes;

    protected $table = 'teaching_load_pull_batch_employees';

    protected $primaryKey = 'teaching_load_pull_batch_employee_id';

    protected $fillable = [
        'teaching_load_pull_batch_id',
        'employee_id',
        'rows_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'rows_count' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TeachingLoadPullBatch::class, 'teaching_load_pull_batch_id', 'teaching_load_pull_batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
