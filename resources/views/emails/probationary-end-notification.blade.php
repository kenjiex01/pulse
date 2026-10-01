<x-mail::message>
@if (trim($body) !== '')
{!! nl2br(e($body)) !!}
@else
Hello,

This is a reminder regarding your probationary employment period.

Thanks,<br>
{{ config('app.name') }}
@endif
</x-mail::message>
