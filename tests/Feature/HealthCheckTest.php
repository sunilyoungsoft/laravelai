<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_api_health_returns_ok_payload(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure([
                'status',
                'app',
                'environment',
                'time_utc',
            ]);
    }

    public function test_framework_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
