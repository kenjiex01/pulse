<?php

namespace App\Support;

class CompanyDocumentOffensePairTable
{
    /**
     * Render a paragraph, turning a line that is only the nature-of-offense and
     * disciplinary-action tags into a bordered two-column table.
     *
     * @param  callable(string): string  $renderText
     * @param  callable(string): string  $resolveTag  Returns escaped HTML for one tag value.
     */
    public static function render(string $text, callable $renderText, callable $resolveTag): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $hasPair = false;

        foreach ($lines as $line) {
            if (self::lineIsPair($line)) {
                $hasPair = true;
                break;
            }
        }

        if (! $hasPair) {
            return $renderText($text);
        }

        $html = '';
        $buffer = [];

        $flush = function () use (&$html, &$buffer, $renderText): void {
            if ($buffer === []) {
                return;
            }

            $html .= $renderText(implode("\n", $buffer));
            $buffer = [];
        };

        foreach ($lines as $line) {
            if (! self::lineIsPair($line)) {
                $buffer[] = $line;

                continue;
            }

            $flush();
            $html .= self::tableHtml(
                (string) $resolveTag('nature_of_offense'),
                (string) $resolveTag('disciplinary_action'),
            );
        }

        $flush();

        return $html;
    }

    public static function lineIsPair(string $line): bool
    {
        if (preg_match_all('/\{\{([a-z0-9_]+)\}\}/i', $line, $matches) < 1) {
            return false;
        }

        $keys = array_map('strtolower', $matches[1]);
        $stripped = trim((string) preg_replace('/\{\{[a-z0-9_]+\}\}/i', '', $line));

        if ($stripped !== '') {
            return false;
        }

        $unique = array_values(array_unique($keys));
        sort($unique);

        return $unique === ['disciplinary_action', 'nature_of_offense'];
    }

    public static function tableHtml(string $natureHtml, string $actionHtml): string
    {
        $cell = 'border:1px solid #111;padding:6px 8px;vertical-align:top;width:50%;text-align:left;';
        $header = $cell.'font-weight:700;background:#f3f4f6;';

        return '<table style="width:100%;border-collapse:collapse;margin:8px 0;white-space:normal;">'
            .'<tr>'
            .'<th style="'.$header.'">Nature of offense</th>'
            .'<th style="'.$header.'">Disciplinary action</th>'
            .'</tr>'
            .'<tr>'
            .'<td style="'.$cell.'">'.$natureHtml.'</td>'
            .'<td style="'.$cell.'">'.$actionHtml.'</td>'
            .'</tr>'
            .'</table>';
    }
}
