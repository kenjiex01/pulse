<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocumentElement extends Model
{
    use SoftDeletes;

    public const TYPE_HEADING = 'heading';
    public const TYPE_PARAGRAPH = 'paragraph';
    public const TYPE_DIVIDER = 'divider';
    public const TYPE_SHORT_TEXT = 'short_text';
    public const TYPE_LONG_TEXT = 'long_text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_EMAIL = 'email';
    public const TYPE_DATE = 'date';
    public const TYPE_DROPDOWN = 'dropdown';
    public const TYPE_RADIO = 'radio';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_YES_NO = 'yes_no';
    public const TYPE_FILE_UPLOAD = 'file_upload';
    public const TYPE_SIGNATURE = 'signature';
    public const TYPE_MERGE_TAG = 'merge_tag';

    protected $table = 'tbl_company_document_elements';

    protected $primaryKey = 'element_id';

    protected $fillable = [
        'company_document_form_id',
        'parent_id',
        'type',
        'label',
        'field_key',
        'placeholder',
        'help_text',
        'is_required',
        'options_json',
        'validation_json',
        'conditional_json',
        'settings_json',
        'width',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'options_json' => 'array',
            'validation_json' => 'array',
            'conditional_json' => 'array',
            'settings_json' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentForm::class, 'company_document_form_id', 'company_document_form_id');
    }

    /**
     * @return array<int, string>
     */
    public static function inputTypes(): array
    {
        return [
            self::TYPE_SHORT_TEXT,
            self::TYPE_LONG_TEXT,
            self::TYPE_NUMBER,
            self::TYPE_EMAIL,
            self::TYPE_DATE,
            self::TYPE_DROPDOWN,
            self::TYPE_RADIO,
            self::TYPE_CHECKBOX,
            self::TYPE_YES_NO,
            self::TYPE_FILE_UPLOAD,
            self::TYPE_SIGNATURE,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function fileBasedTypes(): array
    {
        return [
            self::TYPE_FILE_UPLOAD,
            self::TYPE_SIGNATURE,
        ];
    }

    public function isInput(): bool
    {
        return in_array($this->type, self::inputTypes(), true);
    }

    public function isMergeTag(): bool
    {
        return $this->type === self::TYPE_MERGE_TAG;
    }
}
