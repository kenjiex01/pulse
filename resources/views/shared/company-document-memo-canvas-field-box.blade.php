@php
    $height = (int) ($boxHeight ?? 38);
    $text = (string) ($fieldText ?? '');
@endphp
@if (! empty($pdfBoxes))
    <div class="memo-pdf-field-box{{ $height > 38 ? ' memo-pdf-field-box-long' : '' }}">{!! $text !== '' ? e($text) : '&nbsp;' !!}</div>
@else
    <div style="{{ $boxStyle }}min-height:{{ $height }}px;height:{{ $height }}px;overflow:hidden;">{{ $text }}</div>
@endif
