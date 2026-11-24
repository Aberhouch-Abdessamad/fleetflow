<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base for real-time events. Payloads are plain arrays captured at dispatch
 * time (no tenant-scoped model lookups on the queue). Broadcasting is a
 * notification layer only — the database is always written first.
 */
abstract class TenantBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param list<string> $extraChannels */
    public function __construct(
        public int $companyId,
        public string $companyPublicId,
        public array $payload,
        public array $extraChannels = [],
    ) {}

    public function broadcastOn(): array
    {
        return array_map(
            fn (string $c) => new PrivateChannel($c),
            array_values(array_unique(["company.{$this->companyPublicId}", ...$this->extraChannels]))
        );
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
