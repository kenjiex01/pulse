@php
    $ccLines = is_array($fields['cc_lines'] ?? null) ? $fields['cc_lines'] : [];
@endphp
<table class="letterhead">
    <tr>
        <td class="logo-cell">
            @if (($fields['logo'] ?? '') !== '')
                <img src="{{ $fields['logo'] }}" alt="" width="68" height="66">
            @endif
        </td>
        <td class="name-cell">
            <div class="school">ICCT COLLEGES FOUNDATION, INC.</div>
            <div class="address">V.V. Soliven Avenue II, Cainta, Rizal</div>
        </td>
    </tr>
</table>
<div class="memorandum">M E M O R A N D U M</div>
<table class="flagline">
    <tr>
        <td class="confidential">Confidential</td>
        <td class="control">Control No. {{ $fields['control_no'] ?? '' }}</td>
    </tr>
</table>
<table class="meta">
    <tr>
        <td class="label">From:</td>
        <td class="name">{{ $fields['from_name'] ?? '' }}</td>
        <td class="dash">-</td>
        <td class="title">{{ $fields['from_title'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">To:</td>
        <td class="name">{{ $fields['to_name'] ?? '' }}</td>
        <td class="dash">-</td>
        <td class="title">{{ $fields['to_title'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">Date:</td>
        <td colspan="3">{{ $fields['memo_date'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">Subject:</td>
        <td colspan="3"><b>{{ $subject }}</b></td>
    </tr>
    @if ($ccLines !== [])
        <tr class="cc">
            <td class="label">Cc:</td>
            <td class="name">
                @foreach ($ccLines as $index => $line)
                    @if ($index > 0)<br>@endif{{ $line[0] ?? '' }}
                @endforeach
            </td>
            <td class="dash">
                @foreach ($ccLines as $index => $line)
                    @if ($index > 0)<br>@endif-
                @endforeach
            </td>
            <td>
                @foreach ($ccLines as $index => $line)
                    @if ($index > 0)<br>@endif{{ $line[1] ?? '' }}
                @endforeach
            </td>
        </tr>
    @endif
</table>
<hr class="rule">
