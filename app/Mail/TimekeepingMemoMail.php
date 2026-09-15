<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TimekeepingMemoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $ccRecipients
     * @param  list<array{binary: string, filename: string, mime?: string}>  $emailAttachments
     */
    public function __construct(
        private readonly string $resolvedSubject,
        private readonly string $resolvedBody,
        private readonly string $memoFormName,
        private readonly array $emailAttachments,
        private readonly array $ccRecipients = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->resolvedSubject,
            cc: $this->ccRecipients !== [] ? $this->ccRecipients : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.timekeeping-memo',
            with: [
                'body' => $this->resolvedBody,
                'memoFormName' => $this->memoFormName,
                'attachmentCount' => count($this->emailAttachments),
                'includesNteWord' => $this->includesNteWordAttachment(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $attachment): Attachment => Attachment::fromData(
                fn (): string => $attachment['binary'],
                $attachment['filename'],
            )->withMime((string) ($attachment['mime'] ?? 'application/pdf')),
            $this->emailAttachments,
        );
    }

    private function includesNteWordAttachment(): bool
    {
        foreach ($this->emailAttachments as $attachment) {
            if (str_ends_with(strtolower((string) ($attachment['filename'] ?? '')), '.docx')) {
                return true;
            }
        }

        return false;
    }
}
