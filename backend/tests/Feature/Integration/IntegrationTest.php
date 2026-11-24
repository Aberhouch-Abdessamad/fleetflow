<?php

namespace Tests\Feature\Integration;

use App\Models\Delivery;
use App\Models\SubscriptionPlan;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    private function enterprise($company): void
    {
        $company->currentSubscription->forceFill(['subscription_plan_id' => SubscriptionPlan::where('code', 'enterprise')->value('id')])->save();
    }

    private function order(array $o = []): array
    {
        return array_merge([
            'external_reference' => 'ORDER-1001',
            'pickup' => ['address' => 'Warehouse A, Casablanca'],
            'dropoff' => ['address' => '5 Rue X, Rabat', 'city' => 'Rabat', 'latitude' => 34.02, 'longitude' => -6.84],
            'recipient' => ['name' => 'Sara', 'phone' => '+212600', 'email' => 'sara@example.com'],
            'customer' => ['name' => 'Sara', 'email' => 'sara@example.com'],
            'delivery_fee' => 25, 'items' => [['description' => 'Shoes', 'quantity' => 1, 'weight' => 1.2]],
        ], $o);
    }

    private function client($owner, array $scopes = ['deliveries:create', 'deliveries:read', 'deliveries:cancel']): string
    {
        Sanctum::actingAs($owner);
        $token = $this->postJson('/api/v1/integrations/clients', ['name' => 'Shopify', 'type' => 'ecommerce', 'scopes' => $scopes])->assertCreated()->json('data.token');
        $this->app['auth']->forgetGuards();

        return $token;
    }

    public function test_integrations_require_the_enterprise_feature(): void
    {
        [$owner] = $this->companyOwner();
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/integrations/clients', ['name' => 'X', 'type' => 'ecommerce', 'scopes' => ['deliveries:create']])->assertStatus(402);
    }

    public function test_external_order_creates_delivery_idempotently(): void
    {
        [$owner, $company] = $this->companyOwner();
        $this->enterprise($company);
        $token = $this->client($owner);

        $first = $this->withToken($token)->withHeader('Idempotency-Key', 'key-1')->postJson('/api/v1/integrations/deliveries', $this->order())
            ->assertCreated()->assertJsonPath('data.external_reference', 'ORDER-1001')->assertJsonPath('data.status', 'pending');
        $this->withToken($token)->withHeader('Idempotency-Key', 'key-1')->postJson('/api/v1/integrations/deliveries', $this->order())
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.reference', $first->json('data.reference'));
        $this->withToken($token)->withHeader('Idempotency-Key', 'key-1')->postJson('/api/v1/integrations/deliveries', $this->order(['delivery_fee' => 99]))
            ->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');
        // Same external order sent again with a new key → duplicate prevented.
        $this->withToken($token)->withHeader('Idempotency-Key', 'key-2')->postJson('/api/v1/integrations/deliveries', $this->order())
            ->assertStatus(409)->assertJsonPath('code', 'duplicate_external_reference');
        $this->flushHeaders();
        $this->withToken($token)->postJson('/api/v1/integrations/deliveries', $this->order(['external_reference' => 'X']))->assertStatus(400);

        $this->assertSame(1, Delivery::withoutGlobalScopes()->count());
        $this->assertSame($company->id, Delivery::withoutGlobalScopes()->first()->company_id);
        $this->withToken($token)->getJson('/api/v1/integrations/deliveries/ORDER-1001')->assertOk()->assertJsonPath('data.status', 'pending');
        $this->withToken($token)->postJson('/api/v1/integrations/deliveries/ORDER-1001/cancel', ['reason' => 'Order refunded'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_tokens_are_scoped_and_cannot_cross_surfaces(): void
    {
        [$owner, $company] = $this->companyOwner();
        $this->enterprise($company);
        $token = $this->client($owner, ['deliveries:read']);

        $this->withToken($token)->withHeader('Idempotency-Key', 'k')->postJson('/api/v1/integrations/deliveries', $this->order())->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/vehicles')->assertUnauthorized(); // client token on user API
        $userToken = $owner->createToken('web')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($userToken)->getJson('/api/v1/integrations/deliveries/ORDER-1001')->assertUnauthorized();
    }

    public function test_status_changes_send_signed_webhooks(): void
    {
        Http::fake(['https://shop.example.com/*' => Http::response('ok', 200)]);
        [$owner, $company] = $this->companyOwner();
        $this->enterprise($company);
        Sanctum::actingAs($owner);
        $secret = $this->postJson('/api/v1/integrations/webhooks', ['url' => 'https://shop.example.com/hooks', 'events' => ['delivery.status_updated', 'delivery.created']])->assertCreated()->json('data.secret');

        $this->delivery($company);

        Http::assertSent(function ($request) use ($secret) {
            [$t, $v1] = explode(',', $request->header('X-FleetFlow-Signature')[0]);
            $ts = substr($t, 2);

            return $request->url() === 'https://shop.example.com/hooks'
                && $request->header('X-FleetFlow-Event')[0] === 'delivery.created'
                && substr($v1, 3) === hash_hmac('sha256', $ts.'.'.$request->body(), $secret);
        });
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'delivery.created', 'status' => 'delivered']);
    }

    public function test_failed_webhooks_are_scheduled_for_retry(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);
        [$owner, $company] = $this->companyOwner();
        $this->enterprise($company);
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/integrations/webhooks', ['url' => 'https://shop.example.com/hooks', 'events' => ['*']])->assertCreated();
        $this->delivery($company);

        $row = WebhookDelivery::withoutGlobalScopes()->first();
        $this->assertSame('pending', $row->status);
        $this->assertSame(1, $row->attempts);
        $this->assertNotNull($row->next_attempt_at);
    }
}
