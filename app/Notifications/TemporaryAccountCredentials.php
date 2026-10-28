<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TemporaryAccountCredentials extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $temporaryPassword
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to AgriTimbang - Account Credentials')
            ->greeting('Welcome to AgriTimbang!')
            ->line('An account has been created for you.')
            ->line('Email: ' . $notifiable->email)
            ->line('Temporary Password: ' . $this->temporaryPassword)
            ->line('Please change your password immediately after signing in.')
            ->action('Sign In to AgriTimbang', url('/login'))
            ->line('If you were not expecting this account, please contact your administrator.');
    }
}
