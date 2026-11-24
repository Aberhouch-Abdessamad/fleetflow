<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhook;
use App\Models\ApiClient;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\ActivityLogger;
use App\Services\Subscription\SubscriptionService;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Company admins manage API credentials and webhook endpoints. */
class IntegrationSettingsController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly ActivityLogger $logger, private readonly SubscriptionService $subs) {}

    public function index(): JsonResponse
    {
        return ApiResponse::ok([
            'feature_available' => $this->subs->hasFeature($this->tenant->companyOrFail(), 'api_integrations'),
            'scopes' => ApiClient::SCOPES,
            'events' => WebhookEndpoint::EVENTS,
            'clients' => ApiClient::with('tokens:id,tokenable_id,tokenable_type,name,last_used_at,created_at')->latest()->get()->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'scopes' => $c->scopes, 'is_active' => $c->is_active,
                'last_used_at' => $c->last_used_at?->toIso8601String(), 'tokens' => $c->tokens->count(), 'created_at' => $c->created_at->toIso8601String(),
            ]),
            'webhooks' => WebhookEndpoint::latest()->get()->map(fn ($w) => [
                'id' => $w->id, 'url' => $w->url, 'events' => $w->events, 'is_active' => $w->is_active, 'api_client_id' => $w->api_client_id,
            ]),
        ]);
    }

    public function storeClient(Request $request): JsonResponse
    {
        $this->subs->assertFeature($this->tenant->companyOrFail(), 'api_integrations');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['ecommerce', 'warehouse', 'custom'])],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => [Rule::in(ApiClient::SCOPES)],
        ]);
        $client = new ApiClient($data);
        $client->created_by = $request->user()->id;
        $client->save();
        $token = $client->createToken('default', $client->scopes)->plainTextToken;
        $this->logger->log('integration.client_created', $client, ['name' => $client->name]);

        return ApiResponse::created(['id' => $client->id, 'token' => $token], 'API client created. Copy the token now — it will not be shown again.');
    }

    public function rotateClient(Request $request, ApiClient $client): JsonResponse
    {
        $client->tokens()->delete();
        $token = $client->createToken('default', $client->scopes)->plainTextToken;
        $this->logger->log('integration.token_rotated', $client);

        return ApiResponse::ok(['token' => $token], 'Token rotated.');
    }

    public function destroyClient(ApiClient $client): JsonResponse
    {
        $client->tokens()->delete();
        $client->forceFill(['is_active' => false])->save();
        $this->logger->log('integration.client_revoked', $client);

        return ApiResponse::ok(null, 'API client revoked.');
    }

    public function storeWebhook(Request $request): JsonResponse
    {
        $this->subs->assertFeature($this->tenant->companyOrFail(), 'api_integrations');
        $data = $request->validate([
            'url' => ['required', 'url:https', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in([...WebhookEndpoint::EVENTS, '*'])],
            'api_client_id' => ['nullable', Rule::exists('api_clients', 'id')->where('company_id', $this->tenant->id())],
        ]);
        $secret = 'whsec_'.Str::random(40);
        $hook = WebhookEndpoint::create($data + ['secret' => $secret, 'is_active' => true]);
        $this->logger->log('integration.webhook_created', $hook, ['url' => $hook->url]);

        return ApiResponse::created(['id' => $hook->id, 'secret' => $secret], 'Webhook created. Copy the signing secret now.');
    }

    public function destroyWebhook(WebhookEndpoint $webhook): JsonResponse
    {
        $webhook->forceFill(['is_active' => false])->save();
        $webhook->delete();

        return ApiResponse::ok(null, 'Webhook removed.');
    }

    public function deliveries(Request $request): JsonResponse
    {
        $page = WebhookDelivery::with('endpoint:id,url')->latest('id')->paginate(30);

        return ApiResponse::ok(collect($page->items())->map(fn ($d) => [
            'id' => $d->id, 'event' => $d->event, 'url' => $d->endpoint?->url, 'status' => $d->status, 'attempts' => $d->attempts,
            'response_status' => $d->response_status, 'next_attempt_at' => $d->next_attempt_at?->toIso8601String(),
            'delivered_at' => $d->delivered_at?->toIso8601String(), 'created_at' => $d->created_at->toIso8601String(),
        ]), meta: ['pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]]);
    }

    public function retry(WebhookDelivery $webhookDelivery): JsonResponse
    {
        $webhookDelivery->forceFill(['status' => 'pending', 'next_attempt_at' => now()])->save();
        SendWebhook::dispatch($webhookDelivery->id);

        return ApiResponse::ok(null, 'Retry queued.');
    }
}
