<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Integration\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Delivers one webhook. Failures are retried with exponential backoff (max 6 attempts) by the scheduler. */
class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const MAX_ATTEMPTS = 6;

    public int $tries = 1;

    public function __construct(public int $webhookDeliveryId) {}

    public function handle(): void
    {
        $wd = WebhookDelivery::withoutGlobalScope('company')->with(['endpoint' => fn ($q) => $q->withoutGlobalScope('company')])->find($this->webhookDeliveryId);
        if (! $wd || $wd->status === 'delivered' || ! $wd->endpoint?->is_active) {
            return;
        }

        $body = json_encode($wd->payload, JSON_UNESCAPED_SLASHES);
        $ts = time();
        $attempt = $wd->attempts + 1;
        try {
            $res = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'FleetFlow-Webhooks/1.0',
                'X-FleetFlow-Event' => $wd->event,
                'X-FleetFlow-Delivery' => $wd->event_id,
                'X-FleetFlow-Signature' => WebhookDispatcher::sign($wd->endpoint->secret, $ts, $body),
            ])->withBody($body, 'application/json')->post($wd->endpoint->url);

            $ok = $res->successful();
            $wd->forceFill([
                'attempts' => $attempt,
                'response_status' => $res->status(),
                'response_body' => mb_substr($res->body(), 0, 2000),
                'status' => $ok ? 'delivered' : ($attempt >= self::MAX_ATTEMPTS ? 'failed' : 'pending'),
                'delivered_at' => $ok ? now() : null,
                'next_attempt_at' => $ok || $attempt >= self::MAX_ATTEMPTS ? null : now()->addMinutes(2 ** $attempt),
            ])->save();
        } catch (Throwable $e) {
            $wd->forceFill([
                'attempts' => $attempt,
                'response_status' => null,
                'response_body' => mb_substr($e->getMessage(), 0, 2000),
                'status' => $attempt >= self::MAX_ATTEMPTS ? 'failed' : 'pending',
                'next_attempt_at' => $attempt >= self::MAX_ATTEMPTS ? null : now()->addMinutes(2 ** $attempt),
            ])->save();
        }
    }
}
