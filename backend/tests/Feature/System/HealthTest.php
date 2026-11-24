<?php

namespace Tests\Feature\System;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_reports_a_real_database_round_trip(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonPath('data.database.ok', true);
    }

    public function test_unknown_api_routes_return_the_error_envelope(): void
    {
        $this->getJson('/api/v1/nope')
            ->assertNotFound()
            ->assertJson(['success' => false, 'code' => 'not_found']);
    }
}
