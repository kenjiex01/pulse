<?php

namespace App\Support;

use App\Models\CompanyDocumentForm;
use Carbon\Carbon;

/**
 * ICCT memorandum layout from "FON, ANNA MARIE - ABSENCES & TARDINESS".
 * Preview and the emailed PDF both use this document.
 */
class AbsencesTardinessMemoDocument
{
    public const FORM_CODE = 'hr_memo_absences_tardiness';

    public static function matches(CompanyDocumentForm $form): bool
    {
        return $form->code === self::FORM_CODE;
    }

    /**
     * @param  array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, mixed>
     * }  $preview
     * @return array{
     *     logo_src: string,
     *     control_no: string,
     *     from_name: string,
     *     from_title: string,
     *     to_name: string,
     *     to_title: string,
     *     date: string,
     *     subject: string,
     *     body_period: string,
     *     cc: list<array{name: string, title: string}>
     * }
     */
    public static function data(array $preview): array
    {
        $logoSrc = '';
        $controlNo = '';
        $from = ['', ''];
        $to = ['', ''];
        $date = '';
        $subject = '';
        $ccRaw = '';

        foreach ($preview['elements'] as $element) {
            $type = (string) ($element['type'] ?? '');
            if ($type === 'image' && $logoSrc === '') {
                $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
                $logoSrc = trim((string) ($settings['image_data_url'] ?? $settings['image_file'] ?? ''));

                continue;
            }

            if (! in_array($type, ['paragraph', 'heading'], true)) {
                continue;
            }

            $text = self::plainText((string) ($element['label'] ?? ''));
            if ($text === '') {
                continue;
            }

            if (preg_match('/^Control No\.?\s*(.*)$/i', $text, $matches) === 1) {
                $controlNo = trim($matches[1]);

                continue;
            }

            if (preg_match('/^From:\s*(.*)$/i', $text, $matches) === 1) {
                $from = self::splitParty($matches[1]);

                continue;
            }

            if (preg_match('/^To:\s*(.*)$/i', $text, $matches) === 1) {
                $to = self::splitParty($matches[1]);

                continue;
            }

            if (preg_match('/^Date:\s*(.*)$/i', $text, $matches) === 1) {
                $date = self::formatDate(trim($matches[1]));

                continue;
            }

            if (preg_match('/^Subject:\s*(.*)$/i', $text, $matches) === 1) {
                $subject = trim($matches[1]);

                continue;
            }

            if (preg_match('/^Cc:\s*(.*)$/is', $text, $matches) === 1) {
                $ccRaw = trim($matches[1]);
            }
        }

        if ($subject === '') {
            $subject = 'February and March 2026 Attendances';
        }

        $period = trim((string) preg_replace('/\s+Attendances$/i', '', $subject));

        return [
            'logo_src' => $logoSrc,
            'control_no' => $controlNo !== '' ? $controlNo : '2026-INV/MM-1107',
            'from_name' => $from[0] !== '' ? $from[0] : 'Aurora G. Esma',
            'from_title' => $from[1] !== '' ? $from[1] : 'Vice-President, HR & Administration',
            'to_name' => $to[0],
            'to_title' => $to[1],
            'date' => $date !== '' ? $date : now()->format('F d, Y'),
            'subject' => $subject,
            'body_period' => (string) preg_replace('/\band\b/i', '&', $period, 1),
            'cc' => self::ccLines($ccRaw),
        ];
    }

    /**
     * @return list<array{name: string, title: string}>
     */
    private static function ccLines(string $raw): array
    {
        if ($raw === '') {
            $raw = "Darwin S. Dulay — VP, SPAMO\nJulie Ang — VP, Accounting";
        }

        $lines = [];
        foreach (preg_split("/\r\n|\n|\r/", $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$name, $title] = self::splitParty($line);
            if ($name === '') {
                continue;
            }

            $lines[] = ['name' => $name, 'title' => $title];
        }

        return $lines;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitParty(string $value): array
    {
        $value = trim(str_replace(["\xc2\xa0", '    '], [' ', ' '], $value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        if ($value === '') {
            return ['', ''];
        }

        if (preg_match('/^(.+?)\s+[—–-]\s+(.+)$/u', $value, $matches) === 1) {
            return [trim($matches[1]), trim($matches[2])];
        }

        return [$value, ''];
    }

    private static function formatDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('F d, Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private static function plainText(string $label): string
    {
        $text = html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace(["\xc2\xa0", "\t", "\r"], [' ', ' ', ''], $text));
    }
}
