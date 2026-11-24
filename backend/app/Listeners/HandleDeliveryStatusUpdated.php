<?php

namespace App\Listeners;

use App\Events\DeliveryStatusUpdated;
use App\Models\Company;
use App\Models\Delivery;
use App\Notifications\DeliveryStatusNotification;
use App\Notifications\MailMessageNotification;
use App\Services\Integration\WebhookDispatcher;
use App\Services\Notify;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;

/**
 * Side effects of a status change: in-app notifications for dispatch, an email
 * update to the recipient (if an address is known) and outgoing webhooks.
 */
class HandleDeliveryStatusUpdated
{
    public function __construct(private readonly TenantContext $tenant, private readonly WebhookDispatcher $webhooks) {}

    public function handle(DeliveryStatusUpdated $event): void
    {
        $company = Company::find($event->companyId);
        if (! $company) {
            return;
        }
        $this->tenant->runAs($company, function () use ($event, $company) {
            $p = $event->payload;
            $delivery = Delivery::find($p['delivery_id']);
            if (! $delivery) {
                return;
            }

            if (in_array($p['status'], ['picked_up', 'in_transit', 'delivered', 'failed'], true)) {
                Notify::companyUsersWith($company, 'deliveries.assign',
                    new DeliveryStatusNotification($company->id, $delivery->id, $delivery->reference_number, $p['status'], $p['failure_reason'] ?? null),
                    $delivery->driver?->user_id);
            }

            if ($delivery->recipient_email && in_array($p['status'], ['picked_up', 'in_transit', 'delivered', 'failed'], true)) {
                $url = rtrim((string) config('app.frontend_url'), '/').'/track/'.$delivery->tracking_token;
                $label = str_replace('_', ' ', $p['status']);
                Notification::route('mail', $delivery->recipient_email)->notify(new MailMessageNotification(
                    "Your delivery {$delivery->reference_number} is {$label}",
                    "Hello {$delivery->recipient_name},",
                    ["Your delivery from {$company->name} is now {$label}."],
                    'Track your delivery',
                    $url,
                ));
            }

            $event = match ($p['status']) {
                'delivered' => 'delivery.completed',
                'failed' => 'delivery.failed',
                'cancelled' => 'delivery.cancelled',
                'assigned' => 'delivery.assigned',
                default => $p['previous_status'] === null ? 'delivery.created' : 'delivery.status_updated',
            };
            $this->webhooks->dispatch($company, $event, $delivery, $p);
            if ($event !== 'delivery.status_updated' && $event !== 'delivery.created') {
                $this->webhooks->dispatch($company, 'delivery.status_updated', $delivery, $p);
            }
        });
    }
}
