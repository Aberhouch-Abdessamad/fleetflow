<?php

namespace Tests\Feature\Tenancy;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\TenantNote;
use Tests\TestCase;

class BelongsToCompanyScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('tenant_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('body');
            $t->timestamps();
        });
    }

    public function test_queries_only_see_the_current_tenant_and_fail_closed_without_one(): void
    {
        [, $a] = $this->companyOwner();
        [, $b] = $this->companyOwner();
        $tenant = app(TenantContext::class);

        $tenant->runAs($a, fn () => TenantNote::create(['body' => 'A1']));
        $tenant->runAs($b, fn () => TenantNote::create(['body' => 'B1']));

        $this->assertSame(['A1'], $tenant->runAs($a, fn () => TenantNote::pluck('body')->all()));
        $this->assertSame(['B1'], $tenant->runAs($b, fn () => TenantNote::pluck('body')->all()));
        $this->assertSame(0, TenantNote::count(), 'No tenant → no rows.');
    }

    public function test_company_id_from_input_is_overwritten_on_create_and_frozen_on_update(): void
    {
        [, $a] = $this->companyOwner();
        [, $b] = $this->companyOwner();
        $tenant = app(TenantContext::class);

        $note = $tenant->runAs($a, fn () => TenantNote::create(['body' => 'x', 'company_id' => $b->id]));
        $this->assertSame($a->id, $note->company_id);

        $tenant->runAs($a, fn () => $note->update(['company_id' => $b->id, 'body' => 'y']));
        $this->assertSame($a->id, $note->fresh()->company_id);
    }

    public function test_cross_tenant_lookup_by_id_returns_nothing(): void
    {
        [, $a] = $this->companyOwner();
        [, $b] = $this->companyOwner();
        $tenant = app(TenantContext::class);

        $foreign = $tenant->runAs($b, fn () => TenantNote::create(['body' => 'secret']));
        $this->assertNull($tenant->runAs($a, fn () => TenantNote::find($foreign->id)));
    }
}
