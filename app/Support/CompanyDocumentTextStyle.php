<?php

namespace App\Support;

class CompanyDocumentTextStyle
{
    public const DEFAULT_FONT_SIZE = 14;

    public const DEFAULT_FONT_COLOR = '#4B5563';

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

        $fontColor = strtoupper(trim((string) ($settings['font_color'] ?? self::DEFAULT_FONT_COLOR)));
        if (! preg_match('/^#[0-9A-F]{6}$/', $fontColor)) {
            $fontColor = self::DEFAULT_FONT_COLOR;
        }

        return [
            'font_family' => $fontFamily,
            'font_size' => $fontSize,
            'font_color' => $fontColor,
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
