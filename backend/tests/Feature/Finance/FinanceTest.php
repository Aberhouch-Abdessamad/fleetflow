<?php

namespace Tests\Feature\Finance;

use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\MaintenanceReminder;
use App\Notifications\ExpenseNotification;
use App\Notifications\MaintenanceNotification;
use App\Services\Delivery\DeliveryStatusService;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    public function test_fuel_total_is_computed_and_creates_an_expense(): void
    {
        [$fm, $company] = $this->member('fleet_manager');
        $v = $this->vehicle($company, ['odometer' => 10000]);
        Sanctum::actingAs($fm);

        $this->postJson('/api/v1/fuel-records', ['vehicle_id' => $v->id, 'fuel_type' => 'diesel', 'quantity' => 40.5, 'unit_price' => 12.345, 'odometer' => 10100, 'recorded_at' => now()->subDay()->toIso8601String(), 'total_cost' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('total_cost');
        $this->postJson('/api/v1/fuel-records', ['vehicle_id' => $v->id, 'fuel_type' => 'diesel', 'quantity' => 40.5, 'unit_price' => 12.345, 'odometer' => 10100, 'recorded_at' => now()->subDay()->toIso8601String()])
            ->assertCreated()->assertJsonPath('data.total_cost', '499.97');
        $this->assertSame(10100, $this->reload($v)->odometer);
        $this->assertSame('499.97', Expense::withoutGlobalScopes()->where('source_type', 'fuel_record')->value('amount'));

        $this->postJson('/api/v1/fuel-records', ['vehicle_id' => $v->id, 'fuel_type' => 'diesel', 'quantity' => 10, 'unit_price' => 12, 'odometer' => 9000, 'recorded_at' => now()->toIso8601String()])
            ->assertStatus(422)->assertJsonPath('code', 'odometer_regression');

        $this->postJson('/api/v1/fuel-records', ['vehicle_id' => $v->id, 'fuel_type' => 'diesel', 'quantity' => 30, 'unit_price' => 12, 'odometer' => 10600, 'recorded_at' => now()->toIso8601String()])->assertCreated();
        $this->getJson('/api/v1/fuel-records')->assertOk()->assertJsonPath('meta.summary.l_per_100km', 6);
    }

    public function test_expense_approval_workflow(): void
    {
        Notification::fake();
        [, $company] = $this->member('company_admin');
        [$accountant] = $this->member('accountant', $company);
        $driver = $this->driver($company, [], true);

        Sanctum::actingAs($driver->user);
        $id = $this->postJson('/api/v1/expenses', ['category' => 'parking', 'description' => 'Parking downtown', 'amount' => 15, 'expense_date' => now()->toDateString(), 'driver_id' => 99999, 'status' => 'approved'])
            ->assertStatus(422)->assertJsonValidationErrors(['status']);
        $id = $this->postJson('/api/v1/expenses', ['category' => 'parking', 'description' => 'Parking downtown', 'amount' => 15, 'expense_date' => now()->toDateString()])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.driver.id', $driver->id)->json('data.id');
        Notification::assertSentTo($accountant, ExpenseNotification::class);
        $this->patchJson("/api/v1/expenses/{$id}/approve")->assertForbidden();

        Sanctum::actingAs($accountant);
        $this->patchJson("/api/v1/expenses/{$id}/reject", [])->assertStatus(422);
        $this->patchJson("/api/v1/expenses/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->patchJson("/api/v1/expenses/{$id}/approve")->assertStatus(409);
        Notification::assertSentTo($driver->user, ExpenseNotification::class, fn ($n) => $n->action === 'approved');
        $this->assertDatabaseHas('activity_logs', ['action' => 'expense.approved']);

        // Self-review blocked
        $own = $this->postJson('/api/v1/expenses', ['category' => 'tolls', 'description' => 'Toll', 'amount' => 5, 'expense_date' => now()->toDateString()])->json('data.id');
        $this->patchJson("/api/v1/expenses/{$own}/approve")->assertForbidden()->assertJsonPath('code', 'self_review');
    }

    public function test_maintenance_blocks_assignments_and_completion_updates_vehicle(): void
    {
        [$fm, $company] = $this->member('fleet_manager');
        $v = $this->vehicle($company, ['odometer' => 1000]);
        Sanctum::actingAs($fm);

        $id = $this->postJson('/api/v1/maintenance', ['vehicle_id' => $v->id, 'maintenance_type' => 'regular_service', 'title' => 'Annual service', 'scheduled_date' => now()->toDateString(), 'cost' => 850])
            ->assertCreated()->assertJsonPath('data.status', 'scheduled')->json('data.id');
        $this->putJson("/api/v1/maintenance/{$id}", ['status' => 'in_progress'])->assertOk();
        $this->assertSame('under_maintenance', $this->reload($v)->status->value);

        $this->putJson("/api/v1/maintenance/{$id}", ['status' => 'completed', 'odometer' => 123456, 'next_service_date' => now()->addMonths(6)->toDateString()])->assertOk()->assertJsonPath('data.status', 'completed');
        $v = $this->reload($v);
        $this->assertSame('available', $v->status->value);
        $this->assertSame(123456, $v->odometer);
        $this->assertSame(now()->addMonths(6)->toDateString(), $v->next_service_date->toDateString());
        $this->assertSame('850.00', Expense::withoutGlobalScopes()->where('source_type', 'maintenance_record')->value('amount'));
    }

    public function test_reminder_scheduler_is_idempotent_and_notifies(): void
    {
        Notification::fake();
        [$fm, $company] = $this->member('fleet_manager');
        $this->vehicle($company, ['next_service_date' => now()->subDay()->toDateString(), 'insurance_expiry_date' => now()->addDays(5)->toDateString()]);

        $this->artisan('fleetflow:reminders')->assertSuccessful();
        $this->artisan('fleetflow:reminders')->assertSuccessful();
        $this->assertSame(2, MaintenanceReminder::withoutGlobalScopes()->count());
        Notification::assertSentToTimes($fm, MaintenanceNotification::class, 1);
    }

    public function test_invoices_are_computed_by_the_backend_and_cannot_double_bill(): void
    {
        [$accountant, $company] = $this->member('accountant');
        $customer = $this->within($company, fn () => Customer::factory()->create());
        $driver = $this->driver($company, [], true);
        $vehicle = $this->vehicle($company);
        $deliveries = collect([1, 2])->map(function () use ($company, $customer, $driver, $vehicle) {
            $d = $this->assigned($company, $this->delivery($company, ['customer_id' => $customer->id, 'requires_proof' => false, 'delivery_fee' => '100.00']), $driver, $vehicle);
            $this->within($company, function () use ($d) {
                foreach (['picked_up', 'in_transit', 'arrived', 'delivered'] as $s) {
                    app(DeliveryStatusService::class)->transition(Delivery::find($d->id), DeliveryStatus::from($s), null);
                }
            });

            return $d;
        });
        Sanctum::actingAs($accountant);

        $this->postJson('/api/v1/invoices', ['customer_id' => $customer->id, 'delivery_ids' => $deliveries->pluck('id')->all(), 'total_amount' => 1])->assertStatus(422);
        $inv = $this->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id, 'delivery_ids' => $deliveries->pluck('id')->all(), 'tax_rate' => 20,
            'lines' => [['description' => 'Handling', 'quantity' => 1.5, 'unit_price' => 10]],
        ])->assertCreated()
            ->assertJsonPath('data.subtotal', '215.00')->assertJsonPath('data.tax_amount', '43.00')->assertJsonPath('data.total_amount', '258.00')
            ->json('data');

        $this->postJson('/api/v1/invoices', ['customer_id' => $customer->id, 'delivery_ids' => [$deliveries[0]->id]])->assertStatus(409)->assertJsonPath('code', 'delivery_invoiced');
        $this->postJson("/api/v1/invoices/{$inv['id']}/payments", ['amount' => 10, 'payment_method' => 'cash'])->assertStatus(422)->assertJsonPath('code', 'invoice_not_payable');
        $this->postJson("/api/v1/invoices/{$inv['id']}/issue")->assertOk();
        $this->postJson("/api/v1/invoices/{$inv['id']}/payments", ['amount' => 300, 'payment_method' => 'cash'])->assertStatus(422);
        $this->postJson("/api/v1/invoices/{$inv['id']}/payments", ['amount' => 100, 'payment_method' => 'bank_transfer'])->assertCreated()->assertJsonPath('data.status', 'partially_paid')->assertJsonPath('data.balance', '158.00');
        $this->postJson("/api/v1/invoices/{$inv['id']}/payments", ['amount' => 158, 'payment_method' => 'cash'])->assertCreated()->assertJsonPath('data.status', 'paid');
    }
}
