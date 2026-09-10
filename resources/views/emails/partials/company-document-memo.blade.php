<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:16px;">
    <tr>
        <td style="padding:20px;border:1px solid #e5e7eb;border-radius:12px;background:#ffffff;">
            @if ($form->name)
                <p style="margin:0 0 8px;font-size:18px;font-weight:700;color:#111827;text-align:center;">{{ $form->name }}</p>
            @endif
            @if ($form->description)
                <p style="margin:0 0 16px;font-size:14px;color:#4b5563;text-align:center;white-space:pre-wrap;">{{ $form->description }}</p>
            @endif

            <div style="margin-top:12px;">
                @foreach ($elements as $index => $element)
                    @include('emails.partials.company-document-memo-field', [
                        'element' => $element,
                        'index' => $index,
                        'previewValues' => $previewValues,
                    ])
                @endforeach
            </div>
        </td>
    </tr>
</table>
