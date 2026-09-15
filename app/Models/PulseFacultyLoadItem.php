<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulseFacultyLoadItem extends Model
{
    protected $primaryKey = 'item_id';

    protected $fillable = [
        'upload_id',
        'row_number',
        'row_type',
        'subject_code',
        'title',
        'class_schedule',
        'day',
        'units',
        'hours',
        'hours_paid',
        'room',
        'section',
        'synchronous_schedule',
        'stud_count',
        'period_start',
        'period_end',
        'period_range',
        'schedule_note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'units' => 'integer',
            'hours' => 'integer',
            'hours_paid' => 'integer',
            'stud_count' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(PulseFacultyLoadUpload::class, 'upload_id', 'upload_id');
    }

    public function periodRangeLabel(): ?string
    {
        if ($this->period_range) {
            return $this->period_range;
        }

        if ($this->period_start && $this->period_end) {
            return $this->period_start->format('M j, Y').' — '.$this->period_end->format('M j, Y');
        }

        return null;
    }
}
