@php
    use App\Support\CompanyDocumentMemoPdfLayout;
    use App\Support\CompanyDocumentTextStyle;

    $formSettings = CompanyDocumentTextStyle::normalizeFormSettings($formSettings ?? null);
    $canvasBackground = $formSettings['canvas_background_color'];
    $inputBoxStyle = 'display:block;width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:6px;border-top-left-radius:6px;border-top-right-radius:6px;border-bottom-right-radius:6px;border-bottom-left-radius:6px;background:#ffffff;padding:8px 12px;font-size:14px;line-height:1.45;color:#111827;white-space:pre-wrap;';
    $labelStyle = 'display:block;margin:0 0 4px;font-size:14px;font-weight:600;color:#374151;line-height:1.35;';
    $requiredStyle = 'color:#ef4444;';
@endphp

@php
    $canvasHeightStyle = ! empty($fixedCanvasHeight)
        ? "height:{$layout['canvas_height']}px;"
        : "min-height:{$layout['canvas_height']}px;";
    $pdfBoxes = ! empty($pdfBoxes);
    $canvasWidthCss = $pdfBoxes
        ? ((int) $layout['canvas_width']).'px'
        : '100%';
@endphp

<div
    class="cd-document-preview-readonly"
    style="width:{{ $canvasWidthCss }};max-width:100%;"
>
    <div
        class="cd-designer-canvas-inner"
        style="position:relative;width:{{ $canvasWidthCss }};{{ $canvasHeightStyle }}background-color:{{ $canvasBackground }};"
    >
        @if (! empty($fixedCanvasHeight))
            <div style="width:100%;height:{{ $layout['canvas_height'] }}px;line-height:0;font-size:0;">&nbsp;</div>
        @endif
        @foreach ($layout['elements'] as $index => $element)
            @php
                $box = $element['layout'];
                $type = (string) ($element['type'] ?? '');
                $isImage = $type === 'image';
                $heightStyle = $box['height_px'] ? "height:{$box['height_px']}px;" : '';
                if (! $box['height_px'] && ! empty($box['min_height_px'])) {
                    $heightStyle = "min-height:{$box['min_height_px']}px;";
                }
                $leftStyle = ($box['width_css'] === '100%' && ! $isImage) ? 'left:0;' : "left:{$box['x']}px;";
                $fieldPadding = $isImage ? '0' : '8px';
                $fieldOverflow = $isImage ? 'visible' : ($box['height_px'] ? 'auto' : 'visible');
                $widthCss = $box['width_css'];
                if ($pdfBoxes && $widthCss === '100%' && ! $isImage) {
                    $widthCss = ((int) $layout['canvas_width']).'px';
                }
                $bodyWidthCss = $pdfBoxes ? 'auto' : '100%';
            @endphp
            <div
                style="position:absolute;box-sizing:border-box;min-width:0;{{ $leftStyle }}top:{{ $box['y'] }}px;width:{{ $widthCss }};{{ $heightStyle }}max-width:100%;z-index:{{ $box['z_index'] ?? 10 }};"
            >
                <div style="position:relative;width:{{ $bodyWidthCss }};max-width:100%;min-width:0;{{ $box['height_px'] ? 'height:100%;' : '' }}">
                    <div
                        class="cd-designer-field-body"
                        style="width:{{ $bodyWidthCss }};max-width:100%;min-width:0;padding:{{ $fieldPadding }};overflow:{{ $fieldOverflow }};{{ $box['height_px'] ? 'height:100%;' : '' }}"
                    >
                        @include('shared.company-document-memo-canvas-field', [
                            'element' => $element,
                            'index' => $index,
                            'previewValues' => $previewValues,
                            'layout' => $layout,
                            'inputBoxStyle' => $inputBoxStyle,
                            'labelStyle' => $labelStyle,
                            'requiredStyle' => $requiredStyle,
                            'pdfBoxes' => $pdfBoxes,
                        ])
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
