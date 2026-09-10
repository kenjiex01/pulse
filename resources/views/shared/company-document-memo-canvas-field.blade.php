@php
    use App\Models\CompanyDocumentElement;
    use App\Support\CompanyDocumentMemoPdfLayout;
    use App\Support\CompanyDocumentTextStyle;

    $type = (string) ($element['type'] ?? '');
    $label = (string) ($element['label'] ?? '');
    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
    $displayValue = CompanyDocumentMemoPdfLayout::displayValue($element, $index, $previewValues);
    $previewKey = CompanyDocumentMemoPdfLayout::previewKey($element, $index);
    $signatureValue = $previewValues[$previewKey] ?? null;
    $defaultText = trim((string) ($settings['default_text'] ?? ''));
    $content = $displayValue !== '' ? $displayValue : $defaultText;
    $placeholder = trim((string) ($element['placeholder'] ?? ''));
    $fieldText = $content !== '' ? $content : $placeholder;
    $fieldTextStyle = $content === '' && $placeholder !== '' ? 'color:#9ca3af;' : '';
    $requiredHtml = ! empty($element['is_required']) ? '<span style="'.$requiredStyle.'">*</span>' : '';
    $boxStyle = $inputBoxStyle.($fieldTextStyle !== '' ? $fieldTextStyle : '');
@endphp

@switch($type)
    @case(CompanyDocumentElement::TYPE_HEADING)
        <h3 style="margin:0;width:100%;font-size:18px;font-weight:600;line-height:1.35;color:#111827;word-break:break-word;">{!! $label !!}</h3>
        @break

    @case(CompanyDocumentElement::TYPE_PARAGRAPH)
        @php $paragraphStyle = CompanyDocumentTextStyle::normalize($settings); @endphp
        <p style="margin:0;width:100%;white-space:pre-wrap;overflow-wrap:break-word;font-size:{{ $paragraphStyle['font_size'] }}px;line-height:1.55;color:{{ $paragraphStyle['font_color'] }};">{!! $label !!}</p>
        @break

    @case(CompanyDocumentElement::TYPE_DIVIDER)
        <hr style="margin:0;border:0;border-top:1px solid #e5e7eb;">
        @break

    @case('image')
        @php
            $imageSrc = (string) ($settings['image_file'] ?? $settings['image_data_url'] ?? '');
            $imageWidth = max(40, (int) ($settings['image_width'] ?? 240));
            $imageHeight = max(40, (int) ($settings['image_height'] ?? 160));
            $opacityBaked = ! empty($settings['image_opacity_baked']);
            $opacityStyle = $opacityBaked ? '' : 'opacity:'.CompanyDocumentMemoPdfLayout::imageOpacity($element).';';
        @endphp
        @if ($imageSrc !== '')
            <table width="{{ $imageWidth }}" height="{{ $imageHeight }}" cellpadding="0" cellspacing="0" border="0" style="width:{{ $imageWidth }}px;height:{{ $imageHeight }}px;border-collapse:collapse;{{ $opacityStyle }}">
                <tr>
                    <td width="{{ $imageWidth }}" height="{{ $imageHeight }}" style="width:{{ $imageWidth }}px;height:{{ $imageHeight }}px;padding:0;overflow:visible;line-height:0;font-size:0;">
                        <img
                            src="{{ $imageSrc }}"
                            alt=""
                            width="{{ $imageWidth }}"
                            height="{{ $imageHeight }}"
                            style="display:block;width:{{ $imageWidth }}px;height:{{ $imageHeight }}px;border:0;"
                        >
                    </td>
                </tr>
            </table>
        @endif
        @break

    @case(CompanyDocumentElement::TYPE_SIGNATURE)
        @php
            $signatureSrc = is_array($signatureValue)
                ? trim((string) ($signatureValue['imageFile'] ?? $signatureValue['dataUrl'] ?? ''))
                : '';
        @endphp
        <div style="width:100%;">
            @if ($label !== '')
                <label style="{{ $labelStyle }}">{!! e($label) !!} {!! $requiredHtml !!}</label>
            @endif
            @if ($signatureSrc !== '')
                @if (! empty($pdfBoxes))
                    <div class="memo-pdf-signature-box">
                        <img src="{{ $signatureSrc }}" alt="" width="600" height="104" style="display:block;width:100%;height:104px;">
                    </div>
                @else
                    <div style="overflow:hidden;box-sizing:border-box;border:1px dashed #d1d5db;border-radius:6px;border-top-left-radius:6px;border-top-right-radius:6px;border-bottom-right-radius:6px;border-bottom-left-radius:6px;background:#ffffff;padding:8px;height:120px;">
                        <img src="{{ $signatureSrc }}" alt="" width="600" height="104" style="display:block;width:100%;height:104px;">
                    </div>
                @endif
            @elseif (! empty($pdfBoxes))
                <div class="memo-pdf-signature-box memo-pdf-signature-box-empty">&nbsp;</div>
            @else
                <div style="box-sizing:border-box;height:120px;border:1px dashed #d1d5db;border-radius:6px;border-top-left-radius:6px;border-top-right-radius:6px;border-bottom-right-radius:6px;border-bottom-left-radius:6px;background:#f9fafb;">&nbsp;</div>
            @endif
        </div>
        @break

    @case(CompanyDocumentElement::TYPE_LONG_TEXT)
        <div style="width:100%;">
            @if ($label !== '')
                <label style="{{ $labelStyle }}">{!! e($label) !!} {!! $requiredHtml !!}</label>
            @endif
            @include('shared.company-document-memo-canvas-field-box', [
                'pdfBoxes' => $pdfBoxes ?? false,
                'boxHeight' => 96,
                'fieldText' => $fieldText,
                'fieldTextStyle' => $fieldTextStyle,
                'boxStyle' => $boxStyle,
            ])
        </div>
        @break

    @default
        @if (in_array($type, CompanyDocumentElement::inputTypes(), true))
            <div style="width:100%;">
                @if ($label !== '')
                    <label style="{{ $labelStyle }}">{!! e($label) !!} {!! $requiredHtml !!}</label>
                @endif
                @include('shared.company-document-memo-canvas-field-box', [
                    'pdfBoxes' => $pdfBoxes ?? false,
                    'boxHeight' => 38,
                    'fieldText' => $fieldText,
                    'fieldTextStyle' => $fieldTextStyle,
                    'boxStyle' => $boxStyle,
                ])
            </div>
        @endif
@endswitch

@if (! empty($element['help_text']))
    <p style="margin:4px 0 0;font-size:12px;color:#6b7280;">{{ $element['help_text'] }}</p>
@endif
