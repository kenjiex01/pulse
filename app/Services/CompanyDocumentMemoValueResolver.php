<?php

namespace App\Services;

use App\Models\CompanyDocumentElement;
use App\Models\Employee;

class CompanyDocumentMemoValueResolver
{
    public function __construct(
        private readonly CompanyDocumentMergeTagService $mergeTagService,
    ) {}

    /**
     * @param  array{date_from: string, date_to: string, violation_type: string, violation_count: int, selected_dates: list<string>}  $memoContext
     */
    public function resolveInputValue(CompanyDocumentElement $element, ?Employee $employee, array $memoContext): ?string
    {
        $defaultText = trim((string) (($element->settings_json ?? [])['default_text'] ?? ''));
        if ($defaultText === '') {
            return null;
        }

        return $this->mergeTagService->resolveInlineTags($defaultText, $employee, $memoContext);
    }

    public function signatureDataUrl(CompanyDocumentElement $element): string
    {
        $preview = ($element->settings_json ?? [])['signature_preview'] ?? null;
        if (! is_array($preview)) {
            return '';
        }

        $dataUrl = trim((string) ($preview['dataUrl'] ?? ''));

        return str_starts_with($dataUrl, 'data:image/') ? $dataUrl : '';
    }
}
