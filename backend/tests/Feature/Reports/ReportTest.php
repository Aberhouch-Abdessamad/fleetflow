<?php

namespace Tests\Feature\Reports;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Services\Delivery\DeliveryStatusService;
use App\Services\Expense\ExpenseService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    private function deliver($company, $driver, $vehicle, array $o = []): Delivery
    {
        $d = $this->assigned($company, $this->delivery($company, ['requires_proof' => false] + $o), $driver, $vehicle);
        $this->within($company, function () use ($d) {
            foreach (['picked_up', 'in_transit', 'arrived', 'delivered'] as $s) {
                app(DeliveryStatusService::class)->transition(Delivery::find($d->id), DeliveryStatus::from($s), null);
            }
        });

        return $d;
    }

    public function test_dashboard_kpis_are_calculated_from_records(): void
    {
        [$admin, $company] = $this->member('company_admin');
        $driver = $this->driver($company, [], true);
        $vehicle = $this->vehicle($company);
        $this->vehicle($company);
        $this->deliver($company, $driver, $vehicle, ['delivery_fee' => '80.00']);
        $this->delivery($company);
        // another company's data must not leak in
        [, $other] = $this->member('company_admin');
        $this->vehicle($other);
        $this->delivery($other);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/dashboard?range=today')->assertOk()
            ->assertJsonPath('data.kpis.total_vehicles', 2)
            ->assertJsonPath('data.kpis.total_deliveries', 2)
            ->assertJsonPath('data.kpis.completed_deliveries', 1)
            ->assertJsonPath('data.kpis.pending_deliveries', 1)
            ->assertJsonPath('data.kpis.on_time_rate', 100)
            ->assertJsonPath('data.kpis.delivery_revenue', '80.00');
    }

    public function test_financial_report_flags_unverified_expenses(): void
    {
        [$accountant, $company] = $this->member('accountant');
        $driver = $this->driver($company, [], true);
        $this->deliver($company, $driver, $this->vehicle($company), ['delivery_fee' => '200.00']);
        $this->within($company, fn () => app(ExpenseService::class)->create(['category' => 'tolls', 'description' => 'Toll', 'amount' => '30', 'expense_date' => now()->toDateString()], $company->owner));
        Sanctum::actingAs($accountant);

        $res = $this->getJson('/api/v1/reports/financial?range=this_month')->assertOk()
            ->assertJsonPath('data.summary.delivery_revenue', '200.00')
            ->assertJsonPath('data.summary.approved_expenses', '0.00')
            ->assertJsonPath('data.summary.pending_expenses', '30.00')
            ->assertJsonPath('data.summary.complete', false);
        $this->assertStringContainsString('pending approval', $res->json('data.warnings.0'));

        $this->get('/api/v1/reports/financial?export=csv')->assertOk();
        $this->getJson('/api/v1/reports/fleet')->assertForbidden(); // accountant lacks reports.fleet
    }

    public function test_fleet_and_driver_reports(): void
    {
        [$admin, $company] = $this->member('company_admin');
        $driver = $this->driver($company, [], true);
        $vehicle = $this->vehicle($company);
        $this->deliver($company, $driver, $vehicle);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/reports/fleet?range=last_7_days')->assertOk()->assertJsonPath('data.rows.0.deliveries', 1);
        $this->getJson('/api/v1/reports/drivers')->assertOk()->assertJsonPath('data.rows.0.completed_deliveries', 1)->assertJsonPath('data.rows.0.success_rate', 100);
        $this->getJson('/api/v1/reports/deliveries')->assertOk()->assertJsonPath('data.summary.delivered', 1);
        $xlsx = $this->get('/api/v1/reports/drivers?export=xlsx')->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());
    }
}
