<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerEmailVerificationCode extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your Lumear seller email')
            ->greeting('One more step, '.$notifiable->name.'!')
            ->line('Enter this six-digit code to verify your seller account:')
            ->line('# '.$this->code)
            ->line('This code expires in 10 minutes. If you did not create a seller account, you can ignore this email.');
    }
}
