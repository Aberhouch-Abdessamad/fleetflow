<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Generic transactional email (invitations, verification, password reset, customer updates). */
class MailMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subject,
        public string $greeting,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public array $outro = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)->subject($this->subject)->greeting($this->greeting);
        foreach ($this->lines as $l) {
            $m->line($l);
        }
        if ($this->actionText && $this->actionUrl) {
            $m->action($this->actionText, $this->actionUrl);
        }
        foreach ($this->outro as $l) {
            $m->line($l);
        }

        return $m;
    }
}
