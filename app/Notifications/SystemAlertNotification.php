<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemAlertNotification extends Notification
{
    public function __construct(
        public readonly string $event,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $municipalityId = null,
        public readonly ?string $entityType = null,
        public readonly ?string $entityId = null,
        public readonly bool $email = false,
    ) {}

    public function via(object $notifiable): array { return $this->email ? ['database', 'mail'] : ['database']; }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event, 'title' => $this->title, 'message' => $this->message,
            'municipality_id' => $this->municipalityId, 'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('AgriTimbang: '.$this->title)->line($this->message);
    }
}
