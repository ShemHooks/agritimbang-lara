<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification
{
    public function __construct(private readonly string $code, private readonly string $purpose) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('AgriTimbang verification code')
            ->line('Use this code to confirm your '.$this->purpose.':')
            ->line($this->code)
            ->line('This code expires in 5 minutes. Never share it.');
    }
}
