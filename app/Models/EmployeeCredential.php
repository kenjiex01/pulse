<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class EmployeeCredential extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_employee_credentials';

    protected $primaryKey = 'employee_credential_id';

    protected $fillable = [
        'employee_id',
        'document_type_id',
        'description',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::forceDeleted(function (EmployeeCredential $credential) {
            if ($credential->stored_path !== '' && Storage::disk('local')->exists($credential->stored_path)) {
                Storage::disk('local')->delete($credential->stored_path);
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id', 'document_type_id');
    }

    public function displayLabel(): string
    {
        $typeName = trim((string) ($this->documentType?->type_name ?? ''));
        $description = trim((string) ($this->description ?? ''));

        if ($typeName !== '' && $description !== '' && strcasecmp($typeName, $description) !== 0) {
            return $typeName.' — '.$description;
        }

        return $typeName !== '' ? $typeName : ($description !== '' ? $description : 'Credential');
    }

    public function humanFileSize(): string
    {
        $bytes = max(0, (int) $this->file_size);

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / 1048576, 1).' MB';
    }
}
