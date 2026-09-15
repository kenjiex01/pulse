<?php

namespace App\Support;

class CompanyDocumentTextStyle
{
    public const DEFAULT_FONT_SIZE = 14;

    public const DEFAULT_FONT_COLOR = '#4B5563';

    public const DEFAULT_CANVAS_BACKGROUND = '#FFFFFF';

    public const DEFAULT_LABEL_COLOR = '#374151';

    public const DEFAULT_HEADING_COLOR = '#111827';

    public const DEFAULT_FIELD_TEXT_COLOR = '#111827';

    /** @var array<string, string> */
    public const FONT_FAMILY_OPTIONS = [
        '' => 'System default',
        'Arial, Helvetica, sans-serif' => 'Arial',
        '"Times New Roman", Times, serif' => 'Times New Roman',
        'Georgia, serif' => 'Georgia',
        '"Courier New", Courier, monospace' => 'Courier New',
        'Verdana, Geneva, sans-serif' => 'Verdana',
        'Tahoma, Geneva, sans-serif' => 'Tahoma',
        '"Trebuchet MS", Helvetica, sans-serif' => 'Trebuchet MS',
    ];

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{canvas_background_color: string}
     */
    public static function normalizeFormSettings(?array $settings): array
    {
        return [
            'canvas_background_color' => self::normalizeHexColor(
                $settings['canvas_background_color'] ?? null,
                self::DEFAULT_CANVAS_BACKGROUND,
            ),
        ];
    }

    public static function normalizeHexColor(mixed $value, string $default): string
    {
        if (! is_string($value) || trim($value) === '') {
            return strtoupper($default);
        }

        $normalized = strtoupper(trim($value));

        return preg_match('/^#[0-9A-F]{6}$/', $normalized) ? $normalized : strtoupper($default);
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public static function normalizeLabelColor(?array $settings): string
    {
        return self::normalizeHexColor($settings['label_color'] ?? null, self::DEFAULT_LABEL_COLOR);
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public static function normalizeHeadingColor(?array $settings): string
    {
        return self::normalizeHexColor($settings['font_color'] ?? null, self::DEFAULT_HEADING_COLOR);
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public static function normalizeFieldTextColor(?array $settings): string
    {
        return self::normalizeHexColor($settings['font_color'] ?? null, self::DEFAULT_FIELD_TEXT_COLOR);
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{font_family: string, font_size: int, font_color: string}
     */
    public static function normalize(?array $settings): array
    {
        $fontFamily = trim((string) ($settings['font_family'] ?? ''));
        if (! array_key_exists($fontFamily, self::FONT_FAMILY_OPTIONS)) {
            $fontFamily = '';
        }

        $fontSize = (int) ($settings['font_size'] ?? self::DEFAULT_FONT_SIZE);
        $fontSize = max(8, min(72, $fontSize));

        return [
            'font_family' => $fontFamily,
            'font_size' => $fontSize,
            'font_color' => self::normalizeHexColor($settings['font_color'] ?? null, self::DEFAULT_FONT_COLOR),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public static function inlineStyle(?array $settings): string
    {
        $normalized = self::normalize($settings);

        $parts = [];
        if ($normalized['font_family'] !== '') {
            $parts[] = 'font-family:'.$normalized['font_family'];
        }
        $parts[] = 'font-size:'.$normalized['font_size'].'px';
        $parts[] = 'color:'.$normalized['font_color'];

        return implode(';', $parts).';';
    }
}
