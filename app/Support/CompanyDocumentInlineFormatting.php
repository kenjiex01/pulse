<?php

namespace App\Support;

class CompanyDocumentInlineFormatting
{
    private const ALLOWED_TAG_PATTERN = '/^<\/?(?:b|strong|i|em|u|float-left|float-right)>$/i';

    private const SPLIT_PATTERN = '/(<\/?(?:b|strong|i|em|u|float-left|float-right)>)/i';

    /**
     * Keep only allowed inline formatting tags; drop any other HTML markup.
     */
    public static function sanitize(?string $text): string
    {
        $text = (string) ($text ?? '');

        if ($text === '') {
            return '';
        }

        $parts = preg_split('/(<[^>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }

        $output = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match(self::ALLOWED_TAG_PATTERN, $part) === 1) {
                $output .= strtolower($part);

                continue;
            }

            if (str_starts_with($part, '<')) {
                continue;
            }

            $output .= $part;
        }

        return $output;
    }

    public static function renderSegment(string $text): string
    {
        $text = self::sanitize($text);

        if ($text === '') {
            return '';
        }

        $parts = preg_split(self::SPLIT_PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return e($text);
        }

        $output = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $converted = self::renderAllowedTag($part);
            if ($converted !== null) {
                $output .= $converted;

                continue;
            }

            $output .= e($part);
        }

        return $output;
    }

    private static function renderAllowedTag(string $part): ?string
    {
        $normalized = strtolower($part);

        return match ($normalized) {
            '<float-left>' => '<span style="float:left;">',
            '</float-left>' => '</span>',
            '<float-right>' => '<span style="float:right;">',
            '</float-right>' => '</span>',
            default => preg_match(self::ALLOWED_TAG_PATTERN, $part) === 1 ? $normalized : null,
        };
    }
}
