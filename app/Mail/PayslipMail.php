<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payslip
     */
    public function __construct(
        private readonly array $payslip,
        private readonly string $pdfBinary,
        private readonly string $filename,
    ) {}

    public function envelope(): Envelope
    {
        $period = trim((string) ($this->payslip['pay_period'] ?? ''));

        return new Envelope(
            subject: $period !== ''
                ? 'Payslip — '.$period
                : 'Payslip',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payslip',
            with: [
                'payslip' => $this->payslip,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfBinary, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
