<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $form->name }}</title>
    @php
        use App\Support\CompanyDocumentTextStyle;

        $formSettings = CompanyDocumentTextStyle::normalizeFormSettings($form->settings_json ?? null);
        $canvasBackground = $formSettings['canvas_background_color'];
        $memoPadY = \App\Support\CompanyDocumentMemoPdfLayout::PREVIEW_BODY_PADDING;
        $memoPadX = \App\Support\CompanyDocumentMemoPdfLayout::previewSidePaddingPx();
        $memoFormWidth = \App\Support\CompanyDocumentMemoPdfLayout::legalContentWidthPx();
        $memoPageWidth = \App\Support\CompanyDocumentMemoPdfLayout::legalPageWidthPx();
        $memoPageHeight = \App\Support\CompanyDocumentMemoPdfLayout::legalPageHeightPx();
        $pdfMode = empty($browserPreview);
        $pdfFieldOuterW = \App\Support\CompanyDocumentMemoPdfLayout::pdfFieldBorderBoxWidth($memoFormWidth);
        $pdfFieldContentW = \App\Support\CompanyDocumentMemoPdfLayout::pdfContentBoxSize(
            $pdfFieldOuterW,
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_INPUT_PADDING_X,
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_INPUT_PADDING_X,
        );
        $pdfShortH = \App\Support\CompanyDocumentMemoPdfLayout::pdfContentBoxSize(
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_INPUT_HEIGHT,
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_INPUT_PADDING_Y,
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_INPUT_PADDING_Y,
        );
        $pdfLongH = \App\Support\CompanyDocumentMemoPdfLayout::pdfContentBoxSize(
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_LONG_HEIGHT,
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_INPUT_PADDING_Y,
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_INPUT_PADDING_Y,
        );
        $pdfSigH = \App\Support\CompanyDocumentMemoPdfLayout::pdfContentBoxSize(
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_SIGNATURE_HEIGHT,
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_BODY_PADDING,
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_BODY_PADDING,
        );
        $pdfSigW = \App\Support\CompanyDocumentMemoPdfLayout::pdfContentBoxSize(
            $pdfFieldOuterW,
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_BODY_PADDING,
            \App\Support\CompanyDocumentMemoPdfLayout::FIELD_BODY_PADDING,
        );
    @endphp
    <style>
        {!! '@' !!}page {
            size: legal portrait;
            @if ($pdfMode)
            margin: 0;
            background-color: {{ $canvasBackground }};
            @else
            margin-top: {{ \App\Support\CompanyDocumentMemoPdfLayout::pxToPoints($memoPadY) }}pt;
            margin-right: {{ \App\Support\CompanyDocumentMemoPdfLayout::pxToPoints($memoPadX) }}pt;
            margin-bottom: {{ \App\Support\CompanyDocumentMemoPdfLayout::pxToPoints($memoPadY) }}pt;
            margin-left: {{ \App\Support\CompanyDocumentMemoPdfLayout::pxToPoints($memoPadX) }}pt;
            @endif
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 14px;
            @if ($pdfMode)
            background-color: {{ $canvasBackground }};
            @endif
        }

        * {
            font-family: DejaVu Sans, sans-serif;
            box-sizing: border-box;
        }

        .memo-legal-page {
            width: {{ $pdfMode ? $memoPageWidth.'px' : '100%' }};
            background: {{ $canvasBackground }};
            page-break-inside: avoid;
            @if ($pdfMode)
            height: {{ $memoPageHeight }}px;
            @endif
        }

        .memo-legal-page:not(:last-child) {
            page-break-after: always;
        }

        @if ($pdfMode)
        .memo-legal-page-inner {
            width: {{ $memoPageWidth }}px;
            padding: {{ $memoPadY }}px {{ $memoPadX }}px;
        }
        @endif

        img {
            max-width: none;
        }

        @if ($pdfMode)
        .cd-document-preview-readonly,
        .cd-designer-canvas-inner {
            width: {{ $memoFormWidth }}px;
        }

        .memo-pdf-field-box {
            display: block;
            width: {{ $pdfFieldContentW }}px;
            height: {{ $pdfShortH }}px;
            padding: {{ \App\Support\CompanyDocumentMemoPdfLayout::FIELD_INPUT_PADDING_Y }}px {{ \App\Support\CompanyDocumentMemoPdfLayout::FIELD_INPUT_PADDING_X }}px;
            font-size: 14px;
            line-height: {{ $pdfShortH }}px;
            color: #111827;
            white-space: nowrap;
            overflow: hidden;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #ffffff;
        }

        .memo-pdf-field-box-long {
            height: {{ $pdfLongH }}px;
            line-height: 1.45;
            white-space: pre-wrap;
        }

        .memo-pdf-signature-box {
            display: block;
            width: {{ $pdfSigW }}px;
            height: {{ $pdfSigH }}px;
            padding: {{ \App\Support\CompanyDocumentMemoPdfLayout::FIELD_BODY_PADDING }}px;
            overflow: hidden;
            border: 1px dashed #d1d5db;
            border-radius: 6px;
            background: #ffffff;
        }

        .memo-pdf-signature-box-empty {
            background: #f9fafb;
        }
        @endif

        @if (! empty($browserPreview))
        html, body {
            background: #e5e7eb;
        }

        body {
            padding: 16px;
        }

        .memo-preview-desk {
            width: 100%;
        }

        .memo-legal-page {
            width: 8.5in;
            min-height: 14in;
            margin: 0 auto 16px;
            padding: {{ $memoPadY }}px {{ $memoPadX }}px;
            box-sizing: border-box;
            box-shadow: 0 1px 8px rgba(15, 23, 42, 0.12);
        }
        @endif
    </style>
</head>
<body>
    @php
        $pages = $layout['pages'] ?? [$layout];
    @endphp
    @if (! empty($browserPreview))
        <div class="memo-preview-desk" data-memo-preview-desk>
    @endif
    @foreach ($pages as $page)
        <div class="memo-legal-page">
            @if ($pdfMode)
                <div class="memo-legal-page-inner">
            @endif
            @include('shared.company-document-memo-canvas', [
                'layout' => $page,
                'previewValues' => $previewValues,
                'fixedCanvasHeight' => true,
                'pdfBoxes' => $pdfMode,
                'formSettings' => $formSettings,
            ])
            @if ($pdfMode)
                </div>
            @endif
        </div>
    @endforeach
    @if (! empty($browserPreview))
        </div>
        <script>
            (function () {
                var desk = document.querySelector('[data-memo-preview-desk]');
                if (!desk) {
                    return;
                }

                var fit = function () {
                    var natural = 8.5 * 96;
                    var available = desk.clientWidth || natural;
                    var zoom = Math.min(1, available / natural);
                    desk.style.zoom = String(zoom);
                };

                fit();
                window.addEventListener('resize', fit);
            })();
        </script>
    @endif
</body>
</html>
