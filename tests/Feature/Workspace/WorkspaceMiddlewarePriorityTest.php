<?php

namespace Tests\Feature\Workspace;

use App\Http\Middleware\InitializeTenancyByResolvedDomain;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use ReflectionClass;
use Tests\TestCase;

/**
 * Tenancy resolution must run before authentication in the middleware priority list.
 *
 * The `workspace` guard loads WorkspaceUser on the default connection, which is only the
 * tenant database once tenancy is initialized. If the auth middleware ran first, it would
 * query `workspace_users` on the central `platform` connection (table-not-found). This
 * guards the ordering contract directly — the HTTP-flow tests do not reliably reproduce it
 * because a single test process can resolve tenancy earlier for unrelated reasons.
 */
class WorkspaceMiddlewarePriorityTest extends TestCase
{
    public function test_tenancy_resolution_is_prioritized_before_authentication(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        $property = (new ReflectionClass($kernel))->getProperty('middlewarePriority');
        $property->setAccessible(true);

        /** @var list<string> $priority */
        $priority = $property->getValue($kernel);

        $tenancyIndex = array_search(InitializeTenancyByResolvedDomain::class, $priority, true);
        $authIndex = array_search(AuthenticatesRequests::class, $priority, true);

        $this->assertNotFalse($tenancyIndex, 'Tenancy middleware must be in the priority list.');
        $this->assertNotFalse($authIndex, 'The auth middleware contract must be in the priority list.');
        $this->assertLessThan(
            $authIndex,
            $tenancyIndex,
            'InitializeTenancyByResolvedDomain must be prioritized before authentication.'
        );
    }
}
