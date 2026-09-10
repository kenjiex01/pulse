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
     */
    public function __construct(
        private readonly string $resolvedSubject,
        private readonly string $resolvedBody,
        private readonly string $memoFormName,
        private readonly string $pdfBinary,
        private readonly string $pdfFilename,
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
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfBinary, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
