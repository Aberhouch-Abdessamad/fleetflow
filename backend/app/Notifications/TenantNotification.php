<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Database + broadcast notification carrying its company id and a plain
 * payload built at dispatch time (safe to serialize, no tenant lookups later).
 */
abstract class TenantNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $companyId) {}

    abstract protected function payload(): array;

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function databaseType(object $notifiable): string
    {
        return class_basename(static::class);
    }
}
