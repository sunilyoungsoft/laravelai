<?php

namespace Tests\Feature\Workspace\Support;

use App\Actions\Platform\ProvisionWorkspaceAction;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenancy\Support\GuardedWorkspaceDatabase;

/**
 * Shared test helper for workspace (tenant) feature tests.
 *
 * Wires the `workspace` template connection to the test MySQL, provisions real
 * workspace_{ulid} databases through the guarded helper, and cleans them up. Mirrors
 * the setUp/tearDown pattern established by the provisioning tests so every workspace
 * test enters real tenant context consistently.
 *
 * Host classes must `use RefreshDatabase` and call parent::setUp()/tearDown().
 */
trait ProvisionsWorkspaces
{
    protected GuardedWorkspaceDatabase $workspaceDatabases;

    protected function setUpWorkspaces(): void
    {
        $this->workspaceDatabases = new GuardedWorkspaceDatabase;

        // The dynamic tenant connection is cloned from the `workspace` template; point
        // it at the test MySQL so provisioned workspace databases are reachable.
        config([
            'database.connections.workspace.host' => config('database.connections.platform.host'),
            'database.connections.workspace.port' => config('database.connections.platform.port'),
            'database.connections.workspace.username' => config('database.connections.platform.username'),
            'database.connections.workspace.password' => config('database.connections.platform.password'),
        ]);
        DB::purge('workspace');
    }

    protected function tearDownWorkspaces(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->workspaceDatabases->dropTracked();
    }

    /**
     * Create an active Company with a real, migrated workspace database.
     */
    protected function provisionCompany(?string $slug = null): Company
    {
        $company = Company::factory()->create(
            $slug !== null ? ['slug' => $slug] : []
        );

        $this->workspaceDatabases->trackExisting($company->database_name);

        app(ProvisionWorkspaceAction::class)->execute($company);

        return $company->refresh();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspaces();
    }

    protected function tearDown(): void
    {
        $this->tearDownWorkspaces();

        parent::tearDown();
    }
}
