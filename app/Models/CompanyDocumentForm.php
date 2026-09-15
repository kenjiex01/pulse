<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocumentForm extends Model
{
    use SoftDeletes;

    public const TYPE_MEMO = 'memo';

    protected $table = 'tbl_company_document_forms';

    protected $primaryKey = 'company_document_form_id';

    protected $fillable = [
        'code',
        'name',
        'description',
        'document_type',
        'icct_offense_id',
        'requires_nte',
        'is_nte',
        'allow_multiple_submissions',
        'is_active',
        'submit_label',
        'success_message',
        'settings_json',
        'version',
        'created_by',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'allow_multiple_submissions' => 'boolean',
            'requires_nte' => 'boolean',
            'is_nte' => 'boolean',
            'is_active' => 'boolean',
            'settings_json' => 'array',
            'version' => 'integer',
            'sort_order' => 'integer',
            'icct_offense_id' => 'integer',
        ];
    }

    public function icctOffense(): BelongsTo
    {
        return $this->belongsTo(LuIcctOffense::class, 'icct_offense_id', 'icct_offense_id')->withTrashed();
    }

    /**
     * Default memo field values from the linked ICCT offense (template-level memo type).
     *
     * @return array<string, string>
     */
    public function icctOffenseFieldDefaults(): array
    {
        $offense = $this->relationLoaded('icctOffense')
            ? $this->icctOffense
            : $this->icctOffense()->first();

        if (! $offense) {
            return [];
        }

        return [
            'offense_heading' => $offense->heading_label,
            'offense_section' => $offense->section_code,
            'nature_of_offense' => $offense->dropdownChoiceValue(),
            'offense_category' => $offense->category,
        ];
    }

    public function elements(): HasMany
    {
        return $this->hasMany(CompanyDocumentElement::class, 'company_document_form_id', 'company_document_form_id')
            ->orderBy('sort_order');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(CompanyDocumentApproval::class, 'company_document_form_id', 'company_document_form_id')
            ->orderBy('step_number');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CompanyDocumentSubmission::class, 'company_document_form_id', 'company_document_form_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logSnapshot(): array
    {
        return $this->only([
            'company_document_form_id',
            'code',
            'name',
            'document_type',
            'icct_offense_id',
            'requires_nte',
            'is_nte',
            'is_active',
            'version',
        ]);
    }

    public function getRouteKeyName(): string
    {
        return 'company_document_form_id';
    }

    /**
     * @return array<string, string>
     */
    public static function documentTypes(): array
    {
        return [
            self::TYPE_MEMO => 'Memo',
        ];
    }

    public function supportsApprovalRouting(): bool
    {
        return $this->document_type !== self::TYPE_MEMO;
    }

    public function requiresApproval(): bool
    {
        if (! $this->supportsApprovalRouting()) {
            return false;
        }

        return $this->approvals()->exists();
    }

    public static function activeNteTemplate(): ?self
    {
        return self::query()
            ->where('document_type', self::TYPE_MEMO)
            ->where('is_nte', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();
    }

    public static function conflictingActiveNte(?int $exceptFormId = null): ?self
    {
        return self::query()
            ->where('document_type', self::TYPE_MEMO)
            ->where('is_nte', true)
            ->where('is_active', true)
            ->when($exceptFormId !== null, fn ($query) => $query->where('company_document_form_id', '!=', $exceptFormId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();
    }
}
