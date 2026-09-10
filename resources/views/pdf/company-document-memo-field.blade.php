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
        <div class="memo-heading">{!! $label !!}</div>
        @break

    @case(CompanyDocumentElement::TYPE_PARAGRAPH)
        @php
            $paragraphStyle = CompanyDocumentTextStyle::normalize($settings);
        @endphp
        <div class="memo-paragraph" style="font-size:{{ $paragraphStyle['font_size'] }}px;color:{{ $paragraphStyle['font_color'] }};">{!! $label !!}</div>
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
            <div style="width:{{ $imageWidth }}px;height:{{ $imageHeight }}px;overflow:hidden;opacity:{{ $opacity }};">
                <img
                    src="{{ $imageSrc }}"
                    alt=""
                    width="{{ $imageWidth }}"
                    height="{{ $imageHeight }}"
                    style="display:block;width:{{ $imageWidth }}px;height:{{ $imageHeight }}px;"
                >
            </div>
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
