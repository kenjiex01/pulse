<x-mail::message>
@if (trim($body) !== '')
{!! nl2br(e($body)) !!}

@if (($attachmentCount ?? 1) > 1)
@if ($includesNteWord ?? false)
The memo PDF and Notice to Explain (NTE) Word document are attached to this email.
@else
The memo and Notice to Explain (NTE) are attached to this email.
@endif
@else
The memo PDF is attached to this email.
@endif
@else
Hello,

@if ($memoFormName !== '')
@if (($attachmentCount ?? 1) > 1)
@if ($includesNteWord ?? false)
Your memo for **{{ $memoFormName }}** is attached as a PDF, and the Notice to Explain (NTE) is attached as a Word document.
@else
Your memo for **{{ $memoFormName }}** and the Notice to Explain (NTE) are attached to this email.
@endif
@else
Your memo for **{{ $memoFormName }}** is attached to this email as a PDF file.
@endif
@else
@if (($attachmentCount ?? 1) > 1)
@if ($includesNteWord ?? false)
Your memo is attached as a PDF, and the Notice to Explain (NTE) is attached as a Word document.
@else
Your memo and Notice to Explain (NTE) are attached to this email.
@endif
@else
Your memo is attached to this email as a PDF file.
@endif
@endif
@endif

If you have questions about this memo, please contact your HR or timekeeping office.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
