<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PulseFacultyLoadUpload extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'upload_id';

    protected $fillable = [
        'skolaris_upload_id',
        'faculty_name',
        'employee_number',
        'faculty_email',
        'load_type',
        'department',
        'campus_name',
        'term_label',
        'period_start',
        'period_end',
        'period_range',
        'employment_type',
        'appointment_basis',
        'total_hours_week',
        'total_units',
        'total_hours',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'page_count',
        'parse_status',
        'parse_message',
        'skolaris_updated_at',
        'skolaris_uploader_name',
        'skolaris_uploader_email',
        'uploaded_by_id',
        'pulled_at',
        'pulled_by_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_hours_week' => 'decimal:2',
            'file_size' => 'integer',
            'page_count' => 'integer',
            'total_units' => 'integer',
            'total_hours' => 'integer',
            'skolaris_updated_at' => 'datetime',
            'pulled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PulseFacultyLoadItem::class, 'upload_id', 'upload_id')
            ->orderBy('sort_order')
            ->orderBy('row_number');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function puller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pulled_by_id');
    }

    public static function diskName(): string
    {
        return (string) config('filesystems.pulse_faculty_load_disk', 'local');
    }

    public function parseStatusLabel(): string
    {
        return match ($this->parse_status) {
            'parsed' => 'Parsed',
            'partial' => 'Partial',
            'failed' => 'Failed',
            default => 'Pending',
        };
    }

    public function parseStatusClass(): string
    {
        return match ($this->parse_status) {
            'parsed' => 'bg-emerald-100 text-emerald-700',
            'partial' => 'bg-amber-100 text-amber-800',
            'failed' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }
}
