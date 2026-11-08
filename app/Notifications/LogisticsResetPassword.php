<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class LogisticsResetPassword extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $path = route('logistics.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);

        // Build links using the configured application address.
        $url = rtrim(config('app.url'), '/')
            .'/'
            .ltrim($path, '/');

        return (new MailMessage)
            ->subject('Reset your LUMEAR Logistics password')
            ->view('emails.logistics.password-reset', [
                'name' => $notifiable->name ?? 'LUMEAR Logistics user',
                'url' => $url,
                'expiresIn' => config('auth.passwords.users.expire', 60),
            ])
            ->text('emails.logistics.password-reset-text', [
                'name' => $notifiable->name ?? 'LUMEAR Logistics user',
                'url' => $url,
                'expiresIn' => config('auth.passwords.users.expire', 60),
            ])
            ->withSymfonyMessage(function (Email $message): void {
                $logo = DataPart::fromPath(
                    public_path('images/lumear-logo.png'),
                    'lumear-logo.png',
                    'image/png',
                )->asInline()->setContentId('lumear-logo@lumear');

                $message->addPart($logo);
            });
    }
}
