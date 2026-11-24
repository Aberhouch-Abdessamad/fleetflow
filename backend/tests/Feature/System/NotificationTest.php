<?php

namespace Tests\Feature\System;

use App\Notifications\DeliveryStatusNotification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_notifications_are_scoped_to_the_current_company_and_can_be_marked_read(): void
    {
        [$owner, $companyA] = $this->companyOwner();
        [, $companyB] = $this->companyOwner();
        $this->addMember($companyB, $owner, 'dispatcher');

        $owner->notify(new DeliveryStatusNotification($companyA->id, 1, 'DEL-A', 'delivered', null));
        $owner->notify(new DeliveryStatusNotification($companyB->id, 2, 'DEL-B', 'failed', 'x'));
        Sanctum::actingAs($owner);

        $this->withHeader('X-Company', $companyA->public_id)->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('meta.unread', 1)->assertJsonPath('data.0.data.title', 'Delivery DEL-A is delivered');
        $id = $this->withHeader('X-Company', $companyA->public_id)->getJson('/api/v1/notifications')->json('data.0.id');
        $this->withHeader('X-Company', $companyB->public_id)->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
        $this->withHeader('X-Company', $companyA->public_id)->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->withHeader('X-Company', $companyA->public_id)->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread', 0);
        $this->withHeader('X-Company', $companyB->public_id)->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread', 1);
    }
}
