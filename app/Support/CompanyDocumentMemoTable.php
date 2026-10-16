<?php

namespace App\Support;

class CompanyDocumentMemoTable
{
    public const MAX_ROWS = 12;

    public const MAX_COLUMNS = 6;

    /**
     * @return list<list<string>>
     */
    public static function normalizeRows(mixed $rows): array
    {
        $normalized = [];

        if (is_array($rows)) {
            foreach (array_slice(array_values($rows), 0, self::MAX_ROWS) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $cells = [];
                foreach (array_slice(array_values($row), 0, self::MAX_COLUMNS) as $cell) {
                    $cells[] = CompanyDocumentInlineFormatting::sanitize((string) $cell);
                }

                if ($cells === []) {
                    $cells = [''];
                }

                $normalized[] = $cells;
            }
        }

        if ($normalized === []) {
            return [['Column 1', 'Column 2'], ['', '']];
        }

        $width = max(array_map('count', $normalized));

        foreach ($normalized as $index => $row) {
            $normalized[$index] = array_pad($row, $width, '');
        }

        return $normalized;
    }

    /**
     * @param  list<list<string>>  $rows  Cell values are already safe HTML.
     */
    public static function html(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $cell = 'border:1px solid #111;padding:6px 8px;vertical-align:top;text-align:left;white-space:normal;overflow-wrap:anywhere;word-wrap:break-word;';
        $header = $cell.'font-weight:700;background:#f3f4f6;';
        $useHeader = count($rows) > 1;
        $body = '';

        foreach (array_values($rows) as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }

            $tag = $useHeader && $rowIndex === 0 ? 'th' : 'td';
            $style = $tag === 'th' ? $header : $cell;
            $cells = '';

            foreach ($row as $value) {
                $content = trim((string) $value);
                $cells .= '<'.$tag.' style="'.$style.'">'.($content !== '' ? $content : '&nbsp;').'</'.$tag.'>';
            }

            $body .= '<tr>'.$cells.'</tr>';
        }

        return '<table style="width:100%;border-collapse:collapse;margin:0;table-layout:fixed;white-space:normal;">'.$body.'</table>';
    }

    /**
     * @param  list<list<string>>  $rows
     */
    public static function contentHeight(array $rows, int $contentWidth = 608): int
    {
        if ($rows === []) {
            return 72;
        }

        $columnCount = max(1, count($rows[0] ?? []));
        $columnWidth = max(80, (int) floor(max(160, $contentWidth - 16) / $columnCount));
        $charactersPerLine = max(8, (int) floor($columnWidth / 7));
        $height = 4;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $lines = 1;
            foreach ($row as $cell) {
                $text = trim(html_entity_decode(strip_tags((string) $cell)));
                $length = max(1, mb_strlen($text));
                $lines = max($lines, (int) ceil($length / $charactersPerLine));
            }

            $height += ($lines * 22) + 18;
        }

        return max(72, $height);
    }
}
