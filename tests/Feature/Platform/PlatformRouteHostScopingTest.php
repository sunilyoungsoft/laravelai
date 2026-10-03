<?php

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Platform routes are host-scoped to the central Platform domains (1F-D).
 *
 * The admin login (and the rest of the platform UI) must answer only on a central
 * host — never on a tenant/company host, where the workspace routes take over. This
 * closes the leak where the platform admin login previously showed on every subdomain.
 */
class PlatformRouteHostScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_login_is_served_on_a_central_host(): void
    {
        // `localhost` is a configured central domain.
        $this->get('http://localhost/login')->assertOk();
    }

    public function test_platform_login_is_not_served_on_a_non_central_host(): void
    {
        // A host that is neither central nor a resolvable Company domain must NOT
        // return the platform login. It falls through to the workspace routes, where
        // tenant.resolve aborts 404 for an unknown host (never the admin login).
        $response = $this->get('http://cognizent.laravelai.test/login');

        $response->assertNotFound();
    }

    public function test_platform_dashboard_route_is_not_matched_on_a_non_central_host(): void
    {
        $this->get('http://cognizent.laravelai.test/dashboard')->assertNotFound();
    }
}
