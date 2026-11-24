<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Integration\ExternalDeliveryRequest;
use App\Models\ApiClient;
use App\Models\Delivery;
use App\Services\Delivery\DeliveryService;
use App\Services\Integration\ExternalDeliveryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Machine-to-machine API for e-commerce stores and WMS (/api/v1/integrations/...).
 * Authenticated by a Sanctum token owned by an ApiClient; the tenant is the client's company.
 */
class ExternalApiController extends Controller
{
    public function __construct(private readonly ExternalDeliveryService $external) {}

    private function client(Request $request, string $scope): ApiClient
    {
        $client = $request->user();
        if (! $client instanceof ApiClient || ! $client->is_active) {
            throw new DomainException('Invalid API credentials.', 'unauthenticated', 401);
        }
        if (! $client->tokenCan($scope)) {
            throw new DomainException("This token lacks the {$scope} scope.", 'forbidden', 403);
        }
        $client->forceFill(['last_used_at' => now()])->saveQuietly();

        return $client;
    }

    public function createDelivery(ExternalDeliveryRequest $request): JsonResponse
    {
        return $this->create($request, 'deliveries:create', 'deliveries');
    }

    /** WMS shipment: pickup is the warehouse; same idempotent pipeline. */
    public function createShipment(ExternalDeliveryRequest $request): JsonResponse
    {
        return $this->create($request, 'shipments:write', 'shipments');
    }

    private function create(ExternalDeliveryRequest $request, string $scope, string $endpoint): JsonResponse
    {
        $client = $this->client($request, $scope);
        $key = (string) $request->header('Idempotency-Key');
        if ($key === '' || strlen($key) > 120) {
            throw new DomainException('An Idempotency-Key header (max 120 chars) is required.', 'idempotency_key_required', 400);
        }
        [$status, $body, $replayed] = $this->external->create($client, $key, $request->validated(), $endpoint, fn (Delivery $d) => $this->present($d));

        return response()->json(['success' => $status < 400] + $body, $status, $replayed ? ['Idempotent-Replayed' => 'true'] : []);
    }

    public function showDelivery(Request $request, string $externalReference): JsonResponse
    {
        $this->client($request, 'deliveries:read');
        $d = Delivery::where('external_reference', $externalReference)->firstOrFail();

        return ApiResponse::ok($this->present($d->load('statusHistory')) + [
            'timeline' => $d->statusHistory->map(fn ($h) => ['status' => $h->new_status, 'at' => $h->changed_at->toIso8601String()]),
        ]);
    }

    public function cancelDelivery(Request $request, string $externalReference, DeliveryService $deliveries): JsonResponse
    {
        $this->client($request, 'deliveries:cancel');
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $d = Delivery::where('external_reference', $externalReference)->firstOrFail();
        $deliveries->cancel($d, null, 'Cancelled by integration: '.$data['reason'], 'integration');

        return ApiResponse::ok($this->present($d->fresh()), 'Delivery cancelled.');
    }

    private function present(Delivery $d): array
    {
        return [
            'reference' => $d->reference_number,
            'external_source' => $d->external_source,
            'external_reference' => $d->external_reference,
            'status' => $d->status->value,
            'scheduled_delivery_at' => $d->scheduled_delivery_at?->toIso8601String(),
            'delivered_at' => $d->actual_delivery_at?->toIso8601String(),
            'failure_reason' => $d->failure_reason,
            'tracking_url' => rtrim((string) config('app.frontend_url'), '/').'/track/'.$d->tracking_token,
            'created_at' => $d->created_at->toIso8601String(),
        ];
    }
}
