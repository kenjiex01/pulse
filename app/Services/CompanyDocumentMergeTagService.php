<?php

namespace App\Services;

use App\Models\CompanyDocumentForm;
use App\Models\Employee;
use App\Support\CompanyDocumentMergeTagCatalog;
use App\Support\CompanyDocumentInlineFormatting;
use Illuminate\Support\Carbon;

class CompanyDocumentMergeTagService
{
    public function previewSample(string $tagKey): string
    {
        return CompanyDocumentMergeTagCatalog::sample($tagKey);
    }

    /**
     * @return array<string, string>
     */
    public function previewSamples(?CompanyDocumentForm $form = null): array
    {
        $samples = [];

        foreach (CompanyDocumentMergeTagCatalog::baseTags() as $tagKey => $meta) {
            $samples[$tagKey] = $meta['sample'];
        }

        if ($form !== null && app(CompanyDocumentOffenseMemoService::class)->formHasOffenseNature($form)) {
            $samples = array_merge(
                $samples,
                app(CompanyDocumentOffenseMemoService::class)->previewTagSamples($form),
            );
        }

        return $samples;
    }

    public function resolve(string $tagKey, ?Employee $employee = null, ?array $memoContext = null): string
    {
        if (! CompanyDocumentMergeTagCatalog::isValid($tagKey)) {
            return '';
        }

        if ($memoContext !== null && in_array($tagKey, ['count_of_lates', 'count_of_absences', 'count_of_undertimes'], true)) {
            return (string) ($memoContext['violation_count'] ?? 0);
        }

        if ($memoContext !== null && in_array($tagKey, ['late_dates', 'undertime_dates', 'absent_dates'], true)) {
            return $this->resolveViolationDatesTag($tagKey, $memoContext);
        }

        if ($memoContext !== null && $tagKey === 'disciplinary_action') {
            return (string) ($memoContext['disciplinary_action'] ?? '');
        }

        if ($memoContext !== null && $tagKey === 'offense_frequency') {
            return (string) ($memoContext['offense_frequency_label'] ?? '');
        }

        return match ($tagKey) {
            'employee_full_name' => $employee !== null ? trim($employee->full_name) : $this->previewSample($tagKey),
            'employee_first_name' => $employee !== null ? trim((string) $employee->first_name) : $this->previewSample($tagKey),
            'employee_middle_name' => $employee !== null ? trim((string) ($employee->middle_name ?? '')) : $this->previewSample($tagKey),
            'employee_last_name' => $employee !== null ? trim((string) $employee->last_name) : $this->previewSample($tagKey),
            'employee_number' => $employee !== null ? trim((string) $employee->employee_number) : $this->previewSample($tagKey),
            'count_of_lates', 'count_of_absences', 'count_of_undertimes' => $this->previewSample($tagKey),
            'late_dates', 'undertime_dates', 'absent_dates' => $this->previewSample($tagKey),
            'current_date' => now()->format('F j, Y'),
            'current_time' => now()->format('g:i A'),
            'current_datetime' => now()->format('F j, Y g:i A'),
            'disciplinary_action', 'offense_frequency' => $this->previewSample($tagKey),
            default => $this->previewSample($tagKey),
        };
    }

    public function tagKeyFromElement(?array $settings): ?string
    {
        if (! is_array($settings)) {
            return null;
        }

        $tagKey = trim((string) ($settings['tag_key'] ?? ''));

        return $tagKey !== '' && CompanyDocumentMergeTagCatalog::isValid($tagKey) ? $tagKey : null;
    }

    /**
     * @return array<string, string>
     */
    public function tagLabels(): array
    {
        $labels = [];

        foreach (CompanyDocumentMergeTagCatalog::tags() as $tagKey => $meta) {
            $labels[$tagKey] = $meta['label'];
        }

        return $labels;
    }

    public function inlineToken(string $tagKey): string
    {
        return '{{'.$tagKey.'}}';
    }

    public function renderInlineTagsForDesign(?string $text): string
    {
        $text = (string) ($text ?? '');

        if ($text === '') {
            return '';
        }

        $parts = [];

        foreach ($this->splitInlineTags($text) as $segment) {
            if ($segment['type'] === 'text') {
                $parts[] = CompanyDocumentInlineFormatting::renderSegment($segment['value']);

                continue;
            }

            $label = CompanyDocumentMergeTagCatalog::label($segment['tag_key']);
            $parts[] = '<span class="cd-inline-merge-tag" data-inline-tag="'
                .e($segment['tag_key'])
                .'">'
                .e($label)
                .'</span>';
        }

        return implode('', $parts);
    }

    public function resolveInlineTags(?string $text, ?Employee $employee = null, ?array $memoContext = null): string
    {
        $text = (string) ($text ?? '');

        if ($text === '') {
            return '';
        }

        return preg_replace_callback(
            '/\{\{([a-z0-9_]+)\}\}/i',
            fn (array $matches) => $this->resolve(strtolower($matches[1]), $employee, $memoContext),
            $text,
        ) ?? $text;
    }

    /**
     * @return list<array{type: string, value: string, tag_key?: string}>
     */
    private function splitInlineTags(string $text): array
    {
        $segments = [];
        $offset = 0;

        if (preg_match_all('/\{\{([a-z0-9_]+)\}\}/i', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [['type' => 'text', 'value' => $text]];
        }

        foreach ($matches[0] as $index => $match) {
            $token = (string) $match[0];
            $position = (int) $match[1];
            $tagKey = strtolower((string) $matches[1][$index][0]);

            if ($position > $offset) {
                $segments[] = [
                    'type' => 'text',
                    'value' => substr($text, $offset, $position - $offset),
                ];
            }

            if (CompanyDocumentMergeTagCatalog::isValid($tagKey)) {
                $segments[] = [
                    'type' => 'tag',
                    'value' => $token,
                    'tag_key' => $tagKey,
                ];
            } else {
                $segments[] = [
                    'type' => 'text',
                    'value' => $token,
                ];
            }

            $offset = $position + strlen($token);
        }

        if ($offset < strlen($text)) {
            $segments[] = [
                'type' => 'text',
                'value' => substr($text, $offset),
            ];
        }

        return $segments === [] ? [['type' => 'text', 'value' => $text]] : $segments;
    }

    /**
     * @param  array{violation_type?: string, selected_dates?: list<string>}  $memoContext
     */
    private function resolveViolationDatesTag(string $tagKey, array $memoContext): string
    {
        $typeMap = [
            'late_dates' => 'late',
            'undertime_dates' => 'undertime',
            'absent_dates' => 'absent',
        ];

        if (($memoContext['violation_type'] ?? '') !== ($typeMap[$tagKey] ?? '')) {
            return '';
        }

        $dates = $memoContext['selected_dates'] ?? [];

        return $this->formatDateList(is_array($dates) ? $dates : []);
    }

    /**
     * @param  list<string>  $dates
     */
    private function formatDateList(array $dates): string
    {
        return collect($dates)
            ->filter(static fn ($date) => is_string($date) && $date !== '')
            ->map(static fn (string $date) => Carbon::parse($date)->format('M j, Y'))
            ->values()
            ->join('; ');
    }
}
