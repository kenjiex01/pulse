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
@endphp

@switch($type)
    @case(CompanyDocumentElement::TYPE_HEADING)
        <div class="memo-heading" style="{{ CompanyDocumentTextStyle::headingInlineStyle($settings) }}">{!! $label !!}</div>
        @break

    @case(CompanyDocumentElement::TYPE_PARAGRAPH)
        <div class="memo-paragraph" style="width:100%;overflow:hidden;{{ CompanyDocumentTextStyle::inlineStyle($settings) }}">{!! $label !!}</div>
        @break

    @case(CompanyDocumentElement::TYPE_DIVIDER)
        <hr class="memo-divider">
        @break

    @case('image')
        @php
            $imageSrc = (string) ($settings['image_data_url'] ?? '');
            $imageWidth = max(40, (int) ($settings['image_width'] ?? 240));
            $imageHeight = max(40, (int) ($settings['image_height'] ?? 160));
            $opacity = CompanyDocumentMemoPdfLayout::imageOpacity($element);
        @endphp
        @if ($imageSrc !== '')
            <table width="{{ $imageWidth }}" height="{{ $imageHeight }}" cellpadding="0" cellspacing="0" border="0" style="width:{{ $imageWidth }}px;height:{{ $imageHeight }}px;border-collapse:collapse;opacity:{{ $opacity }};">
                <tr>
                    <td width="{{ $imageWidth }}" height="{{ $imageHeight }}" align="center" valign="middle" style="width:{{ $imageWidth }}px;height:{{ $imageHeight }}px;padding:0;text-align:center;vertical-align:middle;">
                        <img
                            src="{{ $imageSrc }}"
                            alt=""
                            style="display:inline-block;max-width:{{ $imageWidth }}px;max-height:{{ $imageHeight }}px;width:auto;height:auto;"
                        >
                    </td>
                </tr>
            </table>
        @endif
        @break

    @case(CompanyDocumentElement::TYPE_SIGNATURE)
        @php
            $signatureDataUrl = is_array($signatureValue) ? trim((string) ($signatureValue['dataUrl'] ?? '')) : '';
            $signatureWidth = max(200, (int) ($canvasWidth ?? 640) - 16);
        @endphp
        <div class="flex flex-col w-full">
            @if ($label !== '')
                <span class="memo-label">
                    {{ $label }}
                    @if (! empty($element['is_required']))
                        <span class="memo-required">*</span>
                    @endif
                </span>
            @endif
            @if ($signatureDataUrl !== '')
                <div class="memo-signature-box">
                    <img src="{{ $signatureDataUrl }}" alt="" width="{{ $signatureWidth }}" height="104">
                </div>
            @else
                <div class="memo-signature-box" style="line-height:88px;color:#9ca3af;font-size:12px;">
                    No signature
                </div>
            @endif
        </div>
        @break

    @case(CompanyDocumentElement::TYPE_LONG_TEXT)
        @if ($label !== '')
            <span class="memo-label">
                {{ $label }}
                @if (! empty($element['is_required']))
                    <span class="memo-required">*</span>
                @endif
            </span>
        @endif
        <div class="memo-input-box tall">{{ $displayValue !== '' ? $displayValue : '—' }}</div>
        @break

    @default
        @if (in_array($type, CompanyDocumentElement::inputTypes(), true))
            @if ($label !== '')
                <span class="memo-label">
                    {{ $label }}
                    @if (! empty($element['is_required']))
                        <span class="memo-required">*</span>
                    @endif
                </span>
            @endif
            <div class="memo-input-box">{{ $displayValue !== '' ? $displayValue : '—' }}</div>
        @endif
@endswitch

@if (! empty($element['help_text']))
    <div style="margin-top:4px;font-size:10px;color:#6b7280;">{{ $element['help_text'] }}</div>
@endif
