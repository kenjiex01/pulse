<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BiometricMissingUploadHrNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{campus_name: string, campus_code: string}>  $missingCampuses
     */
    public function __construct(
        private readonly string $referenceDateLabel,
        private readonly array $missingCampuses,
        private readonly int $activeCampusCount,
    ) {}

    public function envelope(): Envelope
    {
        $count = count($this->missingCampuses);

        $subject = $count > 0
            ? sprintf('Biometric logs — %d campus(es) with no upload today — %s', $count, $this->referenceDateLabel)
            : sprintf('Biometric logs — all campuses uploaded today — %s', $this->referenceDateLabel);

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.biometric-missing-upload-hr-notification',
            with: [
                'referenceDateLabel' => $this->referenceDateLabel,
                'missingCampuses' => $this->missingCampuses,
                'activeCampusCount' => $this->activeCampusCount,
            ],
        );
    }
}
