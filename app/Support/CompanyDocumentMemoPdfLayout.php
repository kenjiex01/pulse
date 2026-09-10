<?php

namespace App\Support;

use App\Models\CompanyDocumentElement;

class CompanyDocumentMemoPdfLayout
{
    public const CANVAS_INSET = 16;

    public const FIELD_GAP = 12;

    public const MIN_BOX_WIDTH = 80;

    public const MIN_BOX_HEIGHT = 36;

    public const DEFAULT_IMAGE_WIDTH = 240;

    public const DEFAULT_IMAGE_HEIGHT = 160;

    /** Saved designer coordinates were authored on a 640px-wide canvas. */
    public const DESIGNER_REFERENCE_WIDTH = 640;

    public const DEFAULT_CANVAS_WIDTH = 640;

    public const PREVIEW_BODY_PADDING = 16;

    public const FIELD_INPUT_HEIGHT = 38;

    public const FIELD_LONG_HEIGHT = 96;

    public const FIELD_SIGNATURE_HEIGHT = 120;

    public const FIELD_INPUT_PADDING_Y = 8;

    public const FIELD_INPUT_PADDING_X = 12;

    public const FIELD_BODY_PADDING = 8;

    public const FIELD_BORDER_PX = 1;

    /** Legal bond paper (US Legal / long legal): 8.5in × 14in. */
    public const LEGAL_WIDTH_INCHES = 8.5;

    public const LEGAL_HEIGHT_INCHES = 14.0;

    public const PDF_DPI = 96;

    /** @deprecated Use legalContentHeightPx() — kept for tests referencing the constant. */
    public const PDF_MAX_CANVAS_HEIGHT = 980;

    public static function legalPageWidthPx(): int
    {
        return (int) round(self::LEGAL_WIDTH_INCHES * self::PDF_DPI);
    }

    public static function legalPageHeightPx(): int
    {
        return (int) round(self::LEGAL_HEIGHT_INCHES * self::PDF_DPI);
    }

    /**
     * Left/right inset so the form stays at designer width (640px) on 8.5in paper.
     * Tight 16px side padding stretched fields across the full legal sheet.
     */
    public static function previewSidePaddingPx(): int
    {
        return (int) round((self::legalPageWidthPx() - self::DESIGNER_REFERENCE_WIDTH) / 2);
    }

    public static function pxToPoints(int $px): float
    {
        return $px * 72 / self::PDF_DPI;
    }

    public static function legalContentWidthPx(): int
    {
        return self::DESIGNER_REFERENCE_WIDTH;
    }

    public static function legalContentHeightPx(): int
    {
        return self::legalPageHeightPx() - (self::PREVIEW_BODY_PADDING * 2);
    }

    /**
     * Dompdf does not support box-sizing. Convert a browser border-box size to content-box.
     */
    public static function pdfContentBoxSize(int $borderBoxSize, int $paddingStart, int $paddingEnd, int $border = self::FIELD_BORDER_PX): int
    {
        return max(1, $borderBoxSize - $paddingStart - $paddingEnd - ($border * 2));
    }

    public static function pdfFieldBorderBoxWidth(int $canvasWidth = 0): int
    {
        $canvas = $canvasWidth > 0 ? $canvasWidth : self::legalContentWidthPx();

        return max(1, $canvas - (self::FIELD_BODY_PADDING * 2));
    }

    /**
     * Printable canvas height for one legal page.
     */
    public static function maxCanvasHeightForPage(): int
    {
        return self::legalContentHeightPx();
    }

    /**
     * Legal-size canvas: 8.5×14in pages. Content past one page starts a new page.
     *
     * @param  list<array<string, mixed>>  $elements
     * @return array{
     *     canvas_width: int,
     *     canvas_height: int,
     *     page_count: int,
     *     elements: list<array<string, mixed>>,
     *     pages: list<array{canvas_width: int, canvas_height: int, elements: list<array<string, mixed>>}>
     * }
     */
    public static function buildForPdf(array $elements): array
    {
        $layout = self::build(
            $elements,
            self::legalContentWidthPx(),
            self::legalContentWidthPx(),
        );

        return self::paginateLegal($layout);
    }

    /**
     * Dompdf legal paper in points (8.5in × 14in).
     *
     * @return array{width: float, height: float}
     */
    public static function pageSizePoints(int $canvasWidth = 0, int $canvasHeight = 0): array
    {
        return [
            'width' => self::LEGAL_WIDTH_INCHES * 72,
            'height' => self::LEGAL_HEIGHT_INCHES * 72,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @return array{
     *     canvas_width: int,
     *     canvas_height: int,
     *     page_count: int,
     *     elements: list<array<string, mixed>>,
     *     pages: list<array{canvas_width: int, canvas_height: int, elements: list<array<string, mixed>>}>
     * }
     */
    public static function buildForOutput(array $elements): array
    {
        return self::buildForPdf($elements);
    }

    /**
     * @param  array{canvas_width: int, canvas_height: int, elements: list<array<string, mixed>>}  $layout
     * @return array{
     *     canvas_width: int,
     *     canvas_height: int,
     *     page_count: int,
     *     elements: list<array<string, mixed>>,
     *     pages: list<array{canvas_width: int, canvas_height: int, elements: list<array<string, mixed>>}>
     * }
     */
    public static function paginateLegal(array $layout): array
    {
        $pageHeight = self::legalContentHeightPx();
        $buckets = [];

        foreach ($layout['elements'] as $element) {
            if (($element['type'] ?? '') === 'page_break') {
                if ($buckets === []) {
                    $buckets[] = [];
                }
                $buckets[] = [];
                continue;
            }

            $y = (int) ($element['layout']['y'] ?? 0);
            $h = (int) ($element['layout']['height_px'] ?? $element['layout']['min_height_px'] ?? 36);
            $pageIndex = (int) max(0, intdiv(max(0, $y), $pageHeight));
            $yOnPage = $y - ($pageIndex * $pageHeight);

            if ($h > 0 && $h <= $pageHeight && ($yOnPage + $h) > $pageHeight) {
                $pageIndex++;
                $yOnPage = 0;
            }

            $element['layout']['y'] = $yOnPage;
            $buckets[$pageIndex][] = $element;
        }

        if ($buckets === []) {
            $buckets = [[]];
        }

        ksort($buckets);

        $pages = [];
        foreach ($buckets as $pageElements) {
            $pages[] = [
                'canvas_width' => $layout['canvas_width'],
                'canvas_height' => $pageHeight,
                'elements' => $pageElements,
            ];
        }

        return [
            'canvas_width' => $layout['canvas_width'],
            'canvas_height' => $pageHeight,
            'page_count' => count($pages),
            'elements' => $layout['elements'],
            'pages' => $pages,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @return array{
     *     canvas_width: int,
     *     canvas_height: int,
     *     elements: list<array<string, mixed>>
     * }
     */
    public static function build(
        array $elements,
        int $canvasWidth = self::DEFAULT_CANVAS_WIDTH,
        int $referenceWidth = self::DESIGNER_REFERENCE_WIDTH,
    ): array {
        $normalized = self::normalizeElements($elements);
        self::ensurePreviewElementPositions($normalized, $referenceWidth);

        $scale = $canvasWidth / max(1, $referenceWidth);
        $scaledElements = self::scaleElements($normalized, $scale);

        $maxBottom = 360;
        $layoutElements = [];

        foreach ($scaledElements as $index => $element) {
            $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
            $y = (int) ($settings['pos_y'] ?? self::CANVAS_INSET);
            $x = self::fieldPositionX($element);
            $isImage = ($element['type'] ?? '') === 'image';
            if ($isImage) {
                [$element, $x] = self::clampImageToCanvas($canvasWidth, $element, $x);
                $settings = is_array($element['settings'] ?? null) ? $element['settings'] : $settings;
            }
            $box = self::fieldBoxSize($canvasWidth, $element);
            $lockedHeight = $isImage || self::hasCustomBoxHeight($element);
            $elementHeight = $lockedHeight
                ? $box['height']
                : self::estimateElementHeight($element, $canvasWidth);

            $layoutElements[] = array_merge($element, [
                'layout' => [
                    'x' => $x,
                    'y' => $y,
                    'width_css' => self::elementBoxWidthCss($canvasWidth, $element),
                    'height_px' => $lockedHeight ? $box['height'] : null,
                    'min_height_px' => $lockedHeight ? null : $elementHeight,
                    'padding' => $isImage ? 0 : 8,
                    'z_index' => $isImage ? 30 : 10,
                ],
            ]);

            $maxBottom = max($maxBottom, $y + $elementHeight + 48);
        }

        return [
            'canvas_width' => $canvasWidth,
            'canvas_height' => $maxBottom,
            'elements' => $layoutElements,
        ];
    }

    /**
     * @param  array<string, mixed>  $element
     */
    public static function previewKey(array $element, int $index): string
    {
        $fieldKey = trim((string) ($element['field_key'] ?? ''));

        return $fieldKey !== '' ? $fieldKey : 'preview_'.$index;
    }

    /**
     * @param  array<string, mixed>  $element
     * @param  array<string, mixed>  $previewValues
     */
    public static function displayValue(array $element, int $index, array $previewValues): string
    {
        $key = self::previewKey($element, $index);
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
        $defaultText = trim((string) ($settings['default_text'] ?? ''));
        $raw = $previewValues[$key] ?? null;
        $value = is_scalar($raw) ? trim((string) $raw) : '';

        if ($value === '' && $defaultText !== '') {
            $value = $defaultText;
        }

        if (($element['type'] ?? '') === CompanyDocumentElement::TYPE_DATE) {
            if ($value === '' || $value === (string) ($element['label'] ?? '')) {
                return now()->format('m/d/Y');
            }

            try {
                return \Illuminate\Support\Carbon::parse($value)->format('m/d/Y');
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array{width:int,height:int}
     */
    public static function fieldBoxSize(int $containerWidth, array $element): array
    {
        if (($element['type'] ?? '') === 'image') {
            return self::imageDimensions($element);
        }

        return [
            'width' => self::elementBoxWidth($containerWidth, $element),
            'height' => self::hasCustomBoxHeight($element)
                ? max(self::MIN_BOX_HEIGHT, (int) round((float) ($element['settings']['box_height'] ?? 0)))
                : self::estimateElementHeight($element, $containerWidth),
        ];
    }

    /**
     * @param  array<string, mixed>  $element
     */
    public static function fieldPositionX(array $element): int
    {
        if (self::isFullWidth($element)) {
            return 0;
        }

        return (int) ($element['settings']['pos_x'] ?? self::CANVAS_INSET);
    }

    /**
     * @param  array<string, mixed>  $element
     */
    public static function elementBoxWidthCss(int $containerWidth, array $element): string
    {
        if (($element['type'] ?? '') === 'image' || self::hasCustomBoxWidth($element)) {
            return self::elementBoxWidth($containerWidth, $element).'px';
        }

        if (self::isFullWidth($element)) {
            return '100%';
        }

        return match ($element['width'] ?? 'full') {
            'half' => '50%',
            'third' => '33.333%',
            default => '100%',
        };
    }

    /**
     * Keep overlay images inside the 640px canvas so they cannot spill off the right edge.
     *
     * @param  array<string, mixed>  $element
     * @return array{0: array<string, mixed>, 1: int}
     */
    private static function clampImageToCanvas(int $canvasWidth, array $element, int $x): array
    {
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
        $width = max(40, (int) ($settings['image_width'] ?? self::DEFAULT_IMAGE_WIDTH));
        $height = max(40, (int) ($settings['image_height'] ?? self::DEFAULT_IMAGE_HEIGHT));

        if ($width > $canvasWidth) {
            $scale = $canvasWidth / $width;
            $width = $canvasWidth;
            $height = max(40, (int) round($height * $scale));
            $x = 0;
        } elseif ($x + $width > $canvasWidth) {
            $x = max(0, $canvasWidth - $width);
        }

        $settings['image_width'] = $width;
        $settings['image_height'] = $height;

        return [array_merge($element, ['settings' => $settings]), $x];
    }

    /**
     * @param  array<string, mixed>  $element
     */
    public static function imageOpacity(array $element): float
    {
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
        $value = (int) ($settings['image_opacity'] ?? 100);

        return max(0, min(100, $value)) / 100;
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @return list<array<string, mixed>>
     */
    private static function normalizeElements(array $elements): array
    {
        return array_map(function (array $element): array {
            $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];

            return array_merge($element, [
                'settings' => array_merge(['label_align' => 'top'], $settings),
            ]);
        }, $elements);
    }

    /**
     * Mirrors JS `ensurePreviewElementPosition` so PDF matches modal preview stacking.
     *
     * @param  list<array<string, mixed>>  $elements
     */
    private static function ensurePreviewElementPositions(array &$elements, int $referenceWidth): void
    {
        foreach ($elements as $index => &$element) {
            if (self::hasSavedPosition($element)) {
                continue;
            }

            $y = self::CANVAS_INSET;

            for ($i = 0; $i < $index; $i++) {
                $previous = $elements[$i];

                if (self::hasSavedPosition($previous)) {
                    $previousSettings = is_array($previous['settings'] ?? null) ? $previous['settings'] : [];
                    $y = max(
                        $y,
                        (int) ($previousSettings['pos_y'] ?? self::CANVAS_INSET)
                            + self::estimateElementHeight($previous, $referenceWidth)
                            + self::FIELD_GAP,
                    );
                } else {
                    $y += self::estimateElementHeight($previous, $referenceWidth) + self::FIELD_GAP;
                }
            }

            $element['settings']['pos_x'] = self::isFullWidth($element) ? 0 : self::CANVAS_INSET;
            $element['settings']['pos_y'] = $y;
        }

        unset($element);
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @return list<array<string, mixed>>
     */
    private static function scaleElements(array $elements, float $scale): array
    {
        if ($scale === 1.0) {
            return $elements;
        }

        return array_map(function (array $element) use ($scale): array {
            $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];

            foreach (['pos_x', 'pos_y', 'box_width', 'box_height', 'image_width', 'image_height'] as $key) {
                if (! is_numeric($settings[$key] ?? null)) {
                    continue;
                }

                $settings[$key] = (int) round((float) $settings[$key] * $scale);
            }

            return array_merge($element, ['settings' => $settings]);
        }, $elements);
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private static function hasSavedPosition(array $element): bool
    {
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];

        return is_numeric($settings['pos_x'] ?? null) && is_numeric($settings['pos_y'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private static function estimateElementHeight(array $element, int $containerWidth = self::DEFAULT_CANVAS_WIDTH): int
    {
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
        $customHeight = (float) ($settings['box_height'] ?? 0);

        if ($customHeight > 0) {
            return max(self::MIN_BOX_HEIGHT, (int) round($customHeight));
        }

        return match ($element['type'] ?? '') {
            'image' => self::imageDimensions($element)['height'],
            CompanyDocumentElement::TYPE_LONG_TEXT => ((int) ($settings['rows'] ?? 4)) * 24 + 48,
            CompanyDocumentElement::TYPE_HEADING => 40,
            CompanyDocumentElement::TYPE_PARAGRAPH => max(56, self::paragraphLineCount((string) ($element['label'] ?? ''), $containerWidth) * 22 + 16),
            CompanyDocumentElement::TYPE_SIGNATURE => 210,
            'merge_tag' => 44,
            CompanyDocumentElement::TYPE_FILE_UPLOAD => 96,
            default => 76,
        };
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array{width:int,height:int}
     */
    private static function imageDimensions(array $element): array
    {
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];

        return [
            'width' => max(40, (int) ($settings['image_width'] ?? self::DEFAULT_IMAGE_WIDTH)),
            'height' => max(40, (int) ($settings['image_height'] ?? self::DEFAULT_IMAGE_HEIGHT)),
        ];
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private static function elementBoxWidth(int $containerWidth, array $element): int
    {
        if (($element['type'] ?? '') === 'image') {
            return self::imageDimensions($element)['width'];
        }

        if (self::hasCustomBoxWidth($element)) {
            return max(self::MIN_BOX_WIDTH, (int) round((float) ($element['settings']['box_width'] ?? 0)));
        }

        $usable = max($containerWidth, 200);

        return max(120, (int) floor($usable * self::widthRatio((string) ($element['width'] ?? 'full'))));
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private static function isFullWidth(array $element): bool
    {
        if (($element['type'] ?? '') === 'image' || self::hasCustomBoxWidth($element)) {
            return false;
        }

        return ($element['width'] ?? 'full') === 'full';
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private static function hasCustomBoxWidth(array $element): bool
    {
        return is_numeric($element['settings']['box_width'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private static function hasCustomBoxHeight(array $element): bool
    {
        return is_numeric($element['settings']['box_height'] ?? null);
    }

    private static function widthRatio(string $width): float
    {
        return match ($width) {
            'half' => 0.5,
            'third' => 1 / 3,
            default => 1.0,
        };
    }

    private static function paragraphLineCount(string $label, int $containerWidth = self::DEFAULT_CANVAS_WIDTH): int
    {
        $charsPerLine = max(32, (int) floor(max(120, $containerWidth - 32) / 7));
        $lines = preg_split('/\R/', $label) ?: [''];
        $count = 0;

        foreach ($lines as $line) {
            $count += max(1, (int) ceil(strlen($line) / $charsPerLine));
        }

        return $count;
    }
}
