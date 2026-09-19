<?php

namespace App\Support;

class CompanyDocumentMergeTagCatalog
{
    /** @var list<string> */
    public const OFFENSE_TAG_KEYS = [
        'disciplinary_action',
        'offense_frequency',
    ];

    /**
     * @return array<string, array{label: string, sample: string}>
     */
    public static function baseTags(): array
    {
        return [
            'employee_full_name' => [
                'label' => 'Employee Full name',
                'sample' => 'Juan Dela Cruz',
            ],
            'employee_first_name' => [
                'label' => 'Employee First Name',
                'sample' => 'Juan',
            ],
            'employee_middle_name' => [
                'label' => 'Employee Middle Name',
                'sample' => 'Santos',
            ],
            'employee_last_name' => [
                'label' => 'Employee Last Name',
                'sample' => 'Cruz',
            ],
            'employee_number' => [
                'label' => 'Employee number',
                'sample' => 'EMP-00123',
            ],
            'count_of_lates' => [
                'label' => 'Count of lates',
                'sample' => '3',
            ],
            'count_of_absences' => [
                'label' => 'Count of absences',
                'sample' => '1',
            ],
            'count_of_undertimes' => [
                'label' => 'Count of undertimes',
                'sample' => '2',
            ],
            'late_dates' => [
                'label' => 'Late dates',
                'sample' => 'Aug 11, 2026; Aug 12, 2026',
            ],
            'undertime_dates' => [
                'label' => 'Undertime dates',
                'sample' => 'Aug 13, 2026',
            ],
            'absent_dates' => [
                'label' => 'Absent dates',
                'sample' => 'Aug 14, 2026; Aug 15, 2026',
            ],
            'current_date' => [
                'label' => 'Current date',
                'sample' => now()->format('F j, Y'),
            ],
            'current_time' => [
                'label' => 'Current time',
                'sample' => now()->format('g:i A'),
            ],
            'current_datetime' => [
                'label' => 'Current datetime',
                'sample' => now()->format('F j, Y g:i A'),
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, sample: string}>
     */
    public static function offenseTags(): array
    {
        return [
            'disciplinary_action' => [
                'label' => 'Disciplinary action',
                'sample' => 'Written Warning',
            ],
            'offense_frequency' => [
                'label' => 'Offense frequency',
                'sample' => 'Second Offense',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, sample: string}>
     */
    public static function tags(): array
    {
        return array_merge(self::baseTags(), self::offenseTags());
    }

    public static function label(string $tagKey): string
    {
        return self::tags()[$tagKey]['label'] ?? ucfirst(str_replace('_', ' ', $tagKey));
    }

    public static function sample(string $tagKey): string
    {
        return self::tags()[$tagKey]['sample'] ?? self::label($tagKey);
    }

    public static function isValid(string $tagKey): bool
    {
        return array_key_exists($tagKey, self::tags());
    }

    public static function isOffenseTag(string $tagKey): bool
    {
        return in_array($tagKey, self::OFFENSE_TAG_KEYS, true);
    }

    /**
     * @return array<int, array{type: string, label: string, tag_key: string}>
     */
    public static function paletteItems(bool $includeOffenseTags = false): array
    {
        $items = [];
        $source = $includeOffenseTags
            ? array_merge(self::offenseTags(), self::baseTags())
            : self::baseTags();

        foreach ($source as $tagKey => $meta) {
            $items[] = [
                'type' => 'merge_tag',
                'label' => $meta['label'],
                'tag_key' => $tagKey,
            ];
        }

        return $items;
    }
}
