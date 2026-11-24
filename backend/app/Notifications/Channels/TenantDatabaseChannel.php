<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/** Stores the notification's company_id so lists can be filtered per workspace. */
class TenantDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification)
    {
        return parent::buildPayload($notifiable, $notification) + [
            'company_id' => property_exists($notification, 'companyId') ? $notification->companyId : null,
        ];
    }
}
