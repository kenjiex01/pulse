<?php

namespace App\Support;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use RuntimeException;

class CompanyDocumentMemoDocxExporter
{
    /**
     * @param  array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, mixed>
     * }  $preview
     */
    public function export(array $preview, string $assetRoot = ''): string
    {
        $elements = collect($preview['elements'])->sortBy('sort_order')->values()->all();
        $layout = CompanyDocumentMemoPdfLayout::buildForPdf($elements);
        $pages = $layout['pages'] ?? [$layout];
        $previewValues = $preview['preview_values'];

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        foreach ($pages as $pageIndex => $page) {
            $section = $this->addLegalSection($phpWord, $pageIndex > 0);
            $previousBottom = 0;

            foreach ($page['elements'] as $index => $element) {
                if (($element['type'] ?? '') === 'page_break') {
                    continue;
                }

                $box = is_array($element['layout'] ?? null) ? $element['layout'] : [];
                $y = (int) ($box['y'] ?? 0);
                $heightPx = max(
                    20,
                    (int) ($box['height_px'] ?? $box['min_height_px'] ?? CompanyDocumentMemoPdfLayout::MIN_BOX_HEIGHT),
                );
                $this->addVerticalGap($section, $previousBottom, $y);
                $previousBottom = $y + $heightPx;

                $this->addFlowElement(
                    $section,
                    $element,
                    $index,
                    $previewValues,
                    $assetRoot,
                );
            }
        }

        return $this->saveToBinary($phpWord);
    }

    private function addLegalSection(PhpWord $phpWord, bool $breakBefore): Section
    {
        $style = [
            'pageSizeW' => Converter::inchToTwip(CompanyDocumentMemoPdfLayout::LEGAL_WIDTH_INCHES),
            'pageSizeH' => Converter::inchToTwip(CompanyDocumentMemoPdfLayout::LEGAL_HEIGHT_INCHES),
            'marginTop' => Converter::pixelToTwip(CompanyDocumentMemoPdfLayout::PREVIEW_BODY_PADDING),
            'marginBottom' => Converter::pixelToTwip(CompanyDocumentMemoPdfLayout::PREVIEW_BODY_PADDING),
            'marginLeft' => Converter::pixelToTwip(CompanyDocumentMemoPdfLayout::previewSidePaddingPx()),
            'marginRight' => Converter::pixelToTwip(CompanyDocumentMemoPdfLayout::previewSidePaddingPx()),
        ];

        if ($breakBefore) {
            $style['breakType'] = 'nextPage';
        }

        return $phpWord->addSection($style);
    }

    private function addVerticalGap(Section $section, int $previousBottom, int $currentTop): void
    {
        $gapPx = max(0, $currentTop - $previousBottom);
        if ($gapPx < 8) {
            return;
        }

        $section->addTextBreak(0, null, ['spaceAfter' => Converter::pixelToTwip(min($gapPx, 48))]);
    }

    /**
     * @param  array<string, mixed>  $element
     * @param  array<string, mixed>  $previewValues
     */
    private function addFlowElement(
        Section $section,
        array $element,
        int $index,
        array $previewValues,
        string $assetRoot,
    ): void {
        $type = (string) ($element['type'] ?? '');

        match ($type) {
            CompanyDocumentElement::TYPE_HEADING => $this->addHeading($section, $element),
            CompanyDocumentElement::TYPE_PARAGRAPH => $this->addParagraph($section, $element),
            CompanyDocumentElement::TYPE_DIVIDER => $section->addText(str_repeat('—', 72), ['color' => 'D1D5DB'], ['spaceAfter' => 120]),
            'image' => $this->addInlineImage($section, $element, $assetRoot),
            CompanyDocumentElement::TYPE_SIGNATURE => $this->addSignatureField($section, $element, $index, $previewValues, $assetRoot),
            CompanyDocumentElement::TYPE_LONG_TEXT => $this->addInputField($section, $element, $index, $previewValues, multiline: true),
            default => $this->addDefaultField($section, $element, $index, $previewValues, $type),
        };

        $helpText = trim((string) ($element['help_text'] ?? ''));
        if ($helpText !== '' && $type !== CompanyDocumentElement::TYPE_PARAGRAPH) {
            $section->addText($helpText, ['size' => 9, 'color' => '6B7280'], ['spaceAfter' => 120]);
        }
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function addHeading(Section $section, array $element): void
    {
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
        $color = ltrim(CompanyDocumentTextStyle::normalizeHeadingColor($settings), '#');

        $section->addText(strip_tags((string) ($element['label'] ?? '')), [
            'bold' => true,
            'size' => 18,
            'color' => $color,
        ], ['spaceAfter' => 80]);
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function addParagraph(Section $section, array $element): void
    {
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
        $label = trim((string) ($element['label'] ?? ''));
        if ($label === '') {
            return;
        }

        $style = CompanyDocumentTextStyle::normalize($settings);
        $html = CompanyDocumentInlineFormatting::sanitize($label);

        if (str_contains($html, '<')) {
            Html::addHtml(
                $section,
                '<p style="margin:0 0 8px;font-size:'.$style['font_size'].'px;color:'.$style['font_color'].';">'.$html.'</p>',
                false,
                false,
            );

            return;
        }

        foreach (preg_split("/\r\n|\n|\r/", $label) ?: [] as $lineIndex => $line) {
            if ($lineIndex > 0) {
                $section->addTextBreak();
            }
            if ($line !== '') {
                $section->addText($line, [
                    'size' => max(8, min(36, (int) $style['font_size'])),
                    'color' => ltrim((string) $style['font_color'], '#'),
                ], ['spaceAfter' => $lineIndex === count(preg_split("/\r\n|\n|\r/", $label) ?: []) - 1 ? 80 : 0]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $element
     * @param  array<string, mixed>  $previewValues
     */
    private function addInputField(
        Section $section,
        array $element,
        int $index,
        array $previewValues,
        bool $multiline = false,
    ): void {
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
        $label = trim((string) ($element['label'] ?? ''));
        $labelColor = ltrim(CompanyDocumentTextStyle::normalizeLabelColor($settings), '#');
        $displayValue = CompanyDocumentMemoPdfLayout::displayValue($element, $index, $previewValues);
        $placeholder = trim((string) ($element['placeholder'] ?? ''));
        $fieldText = $displayValue !== '' ? $displayValue : $placeholder;
        $isPlaceholder = $displayValue === '' && $placeholder !== '';

        if ($label !== '') {
            $section->addText($label.(! empty($element['is_required']) ? ' *' : ''), [
                'bold' => true,
                'size' => 10,
                'color' => $labelColor,
            ], ['spaceAfter' => 40]);
        }

        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => 'D1D5DB',
            'cellMargin' => 80,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);
        $table->addRow($multiline ? 900 : 450);
        $cell = $table->addCell(null, ['bgColor' => 'FFFFFF']);
        $cell->addText(
            $fieldText !== '' ? $fieldText : ' ',
            [
                'size' => $multiline ? 10 : 11,
                'color' => $isPlaceholder ? '9CA3AF' : ltrim(CompanyDocumentTextStyle::normalizeFieldTextColor($settings), '#'),
            ],
        );

        $section->addTextBreak(0, null, ['spaceAfter' => 120]);
    }

    /**
     * @param  array<string, mixed>  $element
     * @param  array<string, mixed>  $previewValues
     */
    private function addSignatureField(
        Section $section,
        array $element,
        int $index,
        array $previewValues,
        string $assetRoot,
    ): void {
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
        $label = trim((string) ($element['label'] ?? ''));
        $previewKey = CompanyDocumentMemoPdfLayout::previewKey($element, $index);
        $signatureValue = $previewValues[$previewKey] ?? null;

        if ($label !== '') {
            $section->addText($label.(! empty($element['is_required']) ? ' *' : ''), [
                'bold' => true,
                'size' => 10,
                'color' => ltrim(CompanyDocumentTextStyle::normalizeLabelColor($settings), '#'),
            ], ['spaceAfter' => 40]);
        }

        $imagePath = is_array($signatureValue)
            ? $this->resolveSignaturePath($signatureValue, $assetRoot)
            : null;

        if ($imagePath !== null) {
            $section->addImage($imagePath, [
                'width' => 220,
                'height' => 80,
                'alignment' => Jc::LEFT,
            ], false, ['spaceAfter' => 120]);

            return;
        }

        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => 'D1D5DB',
            'borderStyle' => 'dashed',
            'cellMargin' => 80,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);
        $table->addRow(700);
        $table->addCell(null, ['bgColor' => 'F9FAFB'])->addText(' ', ['size' => 10]);
        $section->addTextBreak(0, null, ['spaceAfter' => 120]);
    }

    /**
     * @param  array<string, mixed>  $element
     * @param  array<string, mixed>  $previewValues
     */
    private function addDefaultField(
        Section $section,
        array $element,
        int $index,
        array $previewValues,
        string $type,
    ): void {
        if (! in_array($type, CompanyDocumentElement::inputTypes(), true)) {
            return;
        }

        $this->addInputField($section, $element, $index, $previewValues);
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function addInlineImage(Section $section, array $element, string $assetRoot): void
    {
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
        $path = $this->resolveImagePath($settings, $assetRoot);
        if ($path === null) {
            return;
        }

        $width = max(40, (int) ($settings['image_width'] ?? 240));
        $height = max(40, (int) ($settings['image_height'] ?? 160));

        $section->addImage($path, [
            'width' => $width,
            'height' => $height,
            'alignment' => Jc::CENTER,
        ], false, ['spaceAfter' => 120]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveImagePath(array $settings, string $assetRoot): ?string
    {
        $file = trim((string) ($settings['image_file'] ?? ''));
        if ($file !== '' && $assetRoot !== '') {
            $path = rtrim($assetRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file;

            return is_file($path) ? $path : null;
        }

        $dataUrl = trim((string) ($settings['image_data_url'] ?? ''));
        if ($dataUrl === '') {
            return null;
        }

        return $this->writeDataUrlToTempFile($dataUrl);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function resolveSignaturePath(array $value, string $assetRoot): ?string
    {
        $file = trim((string) ($value['imageFile'] ?? ''));
        if ($file !== '' && $assetRoot !== '') {
            $path = rtrim($assetRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file;

            return is_file($path) ? $path : null;
        }

        $dataUrl = trim((string) ($value['dataUrl'] ?? ''));
        if ($dataUrl === '') {
            return null;
        }

        return $this->writeDataUrlToTempFile($dataUrl);
    }

    private function writeDataUrlToTempFile(string $dataUrl): ?string
    {
        if (! preg_match('/^data:image\/(\w+);base64,(.+)$/', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'pulse-docx-img-');
        if ($path === false) {
            return null;
        }

        $imagePath = $path.'.'.$matches[1];
        rename($path, $imagePath);
        file_put_contents($imagePath, $binary);

        return $imagePath;
    }

    private function saveToBinary(PhpWord $phpWord): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pulse-docx-');
        if ($path === false) {
            throw new RuntimeException('Could not create temporary file for DOCX export.');
        }

        $docxPath = $path.'.docx';
        rename($path, $docxPath);

        try {
            IOFactory::createWriter($phpWord, 'Word2007')->save($docxPath);
            $binary = file_get_contents($docxPath);
            if ($binary === false) {
                throw new RuntimeException('Could not read generated DOCX file.');
            }

            return $binary;
        } finally {
            @unlink($docxPath);
        }
    }
}
