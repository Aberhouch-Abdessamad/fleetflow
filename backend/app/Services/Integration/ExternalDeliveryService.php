<?php

namespace App\Services\Integration;

use App\Exceptions\DomainException;
use App\Models\ApiClient;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\IntegrationRequest;
use App\Services\Delivery\DeliveryService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Creates deliveries from e-commerce orders or WMS shipments with two layers of
 * duplicate protection: the Idempotency-Key header (replays the first response)
 * and the unique (company, external_source, external_reference) index.
 */
class ExternalDeliveryService
{
    public function __construct(private readonly DeliveryService $deliveries, private readonly TenantContext $tenant) {}

    /** @return array{0:int,1:array,2:bool} [status, body, replayed] */
    public function create(ApiClient $client, string $idempotencyKey, array $payload, string $endpoint, callable $present): array
    {
        $hash = hash('sha256', json_encode($payload));
        $existing = IntegrationRequest::where('api_client_id', $client->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->request_hash !== $hash) {
                throw new DomainException('This Idempotency-Key was already used with a different payload.', 'idempotency_conflict', 409);
            }

            return [(int) $existing->response_status, $existing->response_body, true];
        }

        $source = $client->type === 'warehouse' ? 'wms' : ($payload['source'] ?? $client->type);
        $dup = Delivery::withTrashed()->where('external_source', $source)->where('external_reference', $payload['external_reference'])->first();
        if ($dup) {
            $body = ['message' => 'A delivery for this external reference already exists.', 'code' => 'duplicate_external_reference', 'data' => $present($dup)];
            $this->remember($client, $idempotencyKey, $endpoint, $hash, 409, $body, $dup->id);

            return [409, $body, false];
        }

        try {
            return DB::transaction(function () use ($client, $idempotencyKey, $payload, $endpoint, $hash, $source, $present) {
                $customer = null;
                if (! empty($payload['customer'])) {
                    $c = $payload['customer'];
                    $customer = ($c['email'] ?? null) ? Customer::firstOrCreate(['email' => strtolower($c['email'])], ['name' => $c['name'], 'phone' => $c['phone'] ?? null, 'customer_type' => 'individual']) : Customer::create(['name' => $c['name'], 'phone' => $c['phone'] ?? null, 'customer_type' => 'individual']);
                }
                $data = [
                    'customer_id' => $customer?->id,
                    'pickup_address' => $payload['pickup']['address'],
                    'pickup_latitude' => $payload['pickup']['latitude'] ?? null,
                    'pickup_longitude' => $payload['pickup']['longitude'] ?? null,
                    'pickup_contact_name' => $payload['pickup']['contact_name'] ?? null,
                    'pickup_contact_phone' => $payload['pickup']['contact_phone'] ?? null,
                    'delivery_address' => $payload['dropoff']['address'],
                    'delivery_latitude' => $payload['dropoff']['latitude'] ?? null,
                    'delivery_longitude' => $payload['dropoff']['longitude'] ?? null,
                    'delivery_city' => $payload['dropoff']['city'] ?? null,
                    'recipient_name' => $payload['recipient']['name'],
                    'recipient_phone' => $payload['recipient']['phone'] ?? null,
                    'recipient_email' => $payload['recipient']['email'] ?? null,
                    'description' => $payload['description'] ?? null,
                    'scheduled_pickup_at' => $payload['scheduled_pickup_at'] ?? null,
                    'scheduled_delivery_at' => $payload['scheduled_delivery_at'] ?? null,
                    'priority' => $payload['priority'] ?? 'normal',
                    'delivery_fee' => $payload['delivery_fee'] ?? 0,
                    'cod_amount' => $payload['cod_amount'] ?? 0,
                    'delivery_notes' => $payload['notes'] ?? null,
                    'items' => $payload['items'] ?? [],
                ];
                [$delivery] = $this->deliveries->create($this->tenant->companyOrFail(), $data, null, 'integration', [
                    'external_source' => $source, 'external_reference' => $payload['external_reference'],
                ]);
                $body = ['message' => 'Delivery created.', 'data' => $present($delivery)];
                $this->remember($client, $idempotencyKey, $endpoint, $hash, 201, $body, $delivery->id);

                return [201, $body, false];
            });
        } catch (QueryException $e) {
            // Concurrent duplicate — replay whatever won.
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                $existing = IntegrationRequest::where('api_client_id', $client->id)->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return [(int) $existing->response_status, $existing->response_body, true];
                }
                throw new DomainException('A delivery for this external reference already exists.', 'duplicate_external_reference', 409);
            }
            throw $e;
        }
    }

    private function remember(ApiClient $client, string $key, string $endpoint, string $hash, int $status, array $body, ?int $deliveryId): void
    {
        IntegrationRequest::create([
            'api_client_id' => $client->id, 'idempotency_key' => $key, 'endpoint' => $endpoint,
            'request_hash' => $hash, 'response_status' => $status, 'response_body' => $body, 'delivery_id' => $deliveryId,
        ]);
    }
}
