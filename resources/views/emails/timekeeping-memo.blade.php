<x-mail::message>
@if (trim($body) !== '')
{!! nl2br(e($body)) !!}

The memo PDF is attached to this email.
@else
Hello,

@if ($memoFormName !== '')
Your memo for **{{ $memoFormName }}** is attached to this email as a PDF file.
@else
Your memo is attached to this email as a PDF file.
@endif
@endif

If you have questions about this memo, please contact your HR or timekeeping office.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
