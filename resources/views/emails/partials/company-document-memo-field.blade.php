@php
    use App\Models\CompanyDocumentElement;
    use App\Support\CompanyDocumentTextStyle;

    $type = (string) ($element['type'] ?? '');
    $label = (string) ($element['label'] ?? '');
    $fieldKey = (string) ($element['field_key'] ?? '');
    $previewKey = $fieldKey !== '' ? $fieldKey : 'preview_'.$index;
    $value = $previewValues[$previewKey] ?? null;
    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
@endphp

@switch($type)
    @case(CompanyDocumentElement::TYPE_HEADING)
        <h2 style="margin:16px 0 8px;font-size:20px;font-weight:700;color:#111827;">{!! $label !!}</h2>
        @break

    @case(CompanyDocumentElement::TYPE_PARAGRAPH)
        <p style="margin:8px 0;font-size:14px;color:#111827;white-space:pre-wrap;{!! CompanyDocumentTextStyle::inlineStyle($settings) !!}">{!! $label !!}</p>
        @break

    @case(CompanyDocumentElement::TYPE_DIVIDER)
        <hr style="margin:16px 0;border:none;border-top:1px solid #e5e7eb;">
        @break

    @case('image')
        @php
            $imageSrc = (string) ($settings['image_data_url'] ?? '');
        @endphp
        @if ($imageSrc !== '')
            <div style="margin:12px 0;text-align:center;">
                <img src="{{ $imageSrc }}" alt="{{ $label !== '' ? $label : 'Image' }}" style="max-width:100%;height:auto;display:inline-block;">
            </div>
        @endif
        @break

    @case(CompanyDocumentElement::TYPE_SIGNATURE)
        <div style="margin:12px 0;">
            @if ($label !== '')
                <p style="margin:0 0 6px;font-size:13px;font-weight:600;color:#374151;">{{ $label }}</p>
            @endif
            @php
                $signatureDataUrl = is_array($value) ? trim((string) ($value['dataUrl'] ?? '')) : '';
            @endphp
            @if ($signatureDataUrl !== '')
                <div style="padding:8px;border:1px dashed #d1d5db;border-radius:8px;background:#ffffff;">
                    <img src="{{ $signatureDataUrl }}" alt="Signature" style="display:block;max-width:100%;max-height:120px;margin:0 auto;">
                </div>
            @else
                <div style="height:80px;border:1px dashed #d1d5db;border-radius:8px;background:#f9fafb;color:#9ca3af;font-size:12px;line-height:80px;text-align:center;">
                    No signature
                </div>
            @endif
        </div>
        @break

    @case(CompanyDocumentElement::TYPE_LONG_TEXT)
        <div style="margin:12px 0;">
            @if ($label !== '')
                <p style="margin:0 0 6px;font-size:13px;font-weight:600;color:#374151;">{{ $label }}</p>
            @endif
            <div style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;font-size:14px;color:#111827;white-space:pre-wrap;">{{ is_scalar($value) ? $value : '—' }}</div>
        </div>
        @break

    @default
        @if (in_array($type, CompanyDocumentElement::inputTypes(), true))
            <div style="margin:12px 0;">
                @if ($label !== '')
                    <p style="margin:0 0 6px;font-size:13px;font-weight:600;color:#374151;">{{ $label }}</p>
                @endif
                <div style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;font-size:14px;color:#111827;">{{ is_scalar($value) && $value !== '' ? $value : '—' }}</div>
            </div>
        @endif
@endswitch

@if (! empty($element['help_text']))
    <p style="margin:4px 0 0;font-size:12px;color:#6b7280;">{{ $element['help_text'] }}</p>
@endif
