<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Platform\ProvisionWorkspaceAction;
use App\Enums\CompanyStatus;
use App\Enums\DomainStatus;
use App\Models\Company;
use App\Models\Domain;
use App\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenancy\Support\GuardedWorkspaceDatabase;
use Tests\TestCase;

class TenantResolutionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private GuardedWorkspaceDatabase $workspaceDatabases;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceDatabases = new GuardedWorkspaceDatabase;

        config([
            'database.connections.workspace.host' => config('database.connections.platform.host'),
            'database.connections.workspace.port' => config('database.connections.platform.port'),
            'database.connections.workspace.username' => config('database.connections.platform.username'),
            'database.connections.workspace.password' => config('database.connections.platform.password'),
        ]);
        DB::purge('workspace');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->workspaceDatabases->dropTracked();

        parent::tearDown();
    }

    /**
     * Create an active Company with a provisioned workspace DB and an active domain.
     */
    private function provisionedCompanyWithDomain(string $host): Company
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Pending]);
        $this->workspaceDatabases->trackExisting($company->database_name);

        app(ProvisionWorkspaceAction::class)->execute($company);
        $company->refresh();

        Domain::factory()->create([
            'company_id' => $company->id,
            'domain' => $host,
            'status' => DomainStatus::Active,
            'created_by' => PlatformUser::factory(),
        ]);

        return $company;
    }

    public function test_request_on_resolved_host_initializes_the_correct_tenant(): void
    {
        $company = $this->provisionedCompanyWithDomain('acme.example.com');

        $response = $this->get('http://acme.example.com/workspace/ping');

        $response->assertOk();
        $response->assertJson([
            'tenant' => $company->id,
            'database' => $company->database_name,
        ]);
    }

    public function test_unknown_host_fails_safely_with_404(): void
    {
        $this->get('http://nobody.example.com/workspace/ping')->assertNotFound();

        $this->assertFalse(tenancy()->initialized);
        $this->assertSame('platform', config('database.default'));
    }

    public function test_platform_stays_central_after_unresolved_request(): void
    {
        $this->get('http://nobody.example.com/workspace/ping');

        $this->assertSame('laravelai_platform_testing', DB::connection('platform')->getDatabaseName());
    }

    public function test_domain_of_company_a_cannot_initialize_company_b(): void
    {
        $a = $this->provisionedCompanyWithDomain('a.example.com');
        $b = $this->provisionedCompanyWithDomain('b.example.com');

        $this->get('http://a.example.com/workspace/ping')
            ->assertOk()
            ->assertJson(['tenant' => $a->id, 'database' => $a->database_name]);

        // Ending happens in tearDown/each request; verify B resolves to B only.
        $this->get('http://b.example.com/workspace/ping')
            ->assertOk()
            ->assertJson(['tenant' => $b->id, 'database' => $b->database_name]);
    }

    public function test_client_cannot_select_workspace_via_request_input(): void
    {
        $a = $this->provisionedCompanyWithDomain('a2.example.com');
        $b = $this->provisionedCompanyWithDomain('b2.example.com');

        // Attempt to override tenant selection with client-supplied identifiers on host A.
        $response = $this->get('http://a2.example.com/workspace/ping?'.http_build_query([
            'company_id' => $b->id,
            'tenant_id' => $b->id,
            'database' => $b->database_name,
        ]));

        // Resolution is driven only by the host; the request lands on A, not B.
        $response->assertOk()->assertJson(['tenant' => $a->id, 'database' => $a->database_name]);
    }
}
