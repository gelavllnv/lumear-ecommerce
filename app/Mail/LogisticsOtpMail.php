<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class LogisticsOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code)
    {
        $this->withSymfonyMessage(function (Email $message): void {
            $logo = DataPart::fromPath(
                public_path('images/lumear-logo.png'),
                'lumear-logo.png',
                'image/png',
            )->asInline()->setContentId('lumear-logo@lumear');

            $message->addPart($logo);
        });
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your LUMEAR Logistics verification code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.logistics.otp',
            text: 'emails.logistics.otp-text',
            with: ['code' => $this->code],
        );
    }
}
