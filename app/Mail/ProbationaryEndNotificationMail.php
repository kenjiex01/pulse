<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProbationaryEndNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $resolvedSubject,
        private readonly string $resolvedBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->resolvedSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.probationary-end-notification',
            with: [
                'body' => $this->resolvedBody,
            ],
        );
    }
}
