<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeachingLoadPullBatch extends Model
{
    use SoftDeletes;

    protected $table = "teaching_load_pull_batches";

    protected $primaryKey = "teaching_load_pull_batch_id";

    protected $fillable = [
        "batch_no",
        "date_from",
        "date_to",
        "employee_count",
        "records_count",
        "pulled_by_id",
        "pulled_at",
    ];

    protected function casts(): array
    {
        return [
            "batch_no" => "integer",
            "employee_count" => "integer",
            "records_count" => "integer",
            "date_from" => "date",
            "date_to" => "date",
            "pulled_at" => "datetime",
        ];
    }

    public function pulledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, "pulled_by_id");
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TeachingLoadSession::class, "teaching_load_pull_batch_id", "teaching_load_pull_batch_id");
    }

    public function formattedBatchNo(): string
    {
        return str_pad((string) $this->batch_no, 4, "0", STR_PAD_LEFT);
    }

    public function dateRangeLabel(): string
    {
        if (! $this->date_from || ! $this->date_to) {
            return "—";
        }

        return $this->date_from->format("M j, Y")." – ".$this->date_to->format("M j, Y");
    }
}
