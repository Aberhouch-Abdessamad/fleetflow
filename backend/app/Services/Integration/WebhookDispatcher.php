<?php

namespace App\Services\Integration;

use App\Jobs\SendWebhook;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    public function dispatch(Company $company, string $event, Delivery $delivery, array $payload): void
    {
        $endpoints = WebhookEndpoint::where('company_id', $company->id)->where('is_active', true)->get()
            ->filter(fn (WebhookEndpoint $e) => in_array($event, $e->events ?? [], true) || in_array('*', $e->events ?? [], true));

        foreach ($endpoints as $endpoint) {
            $record = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event_id' => (string) Str::uuid(),
                'event' => $event,
                'payload' => [
                    'event' => $event,
                    'occurred_at' => now()->toIso8601String(),
                    'data' => [
                        'reference' => $delivery->reference_number,
                        'external_source' => $delivery->external_source,
                        'external_reference' => $delivery->external_reference,
                        'status' => $delivery->status->value,
                        'previous_status' => $payload['previous_status'] ?? null,
                        'failure_reason' => $delivery->failure_reason,
                        'delivered_at' => $delivery->actual_delivery_at?->toIso8601String(),
                        'tracking_url' => rtrim((string) config('app.frontend_url'), '/').'/track/'.$delivery->tracking_token,
                    ],
                ],
                'status' => 'pending',
                'next_attempt_at' => now(),
            ]);
            SendWebhook::dispatch($record->id)->afterCommit();
        }
    }

    /** HMAC-SHA256 over "{timestamp}.{body}" — receivers verify with the endpoint secret. */
    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
