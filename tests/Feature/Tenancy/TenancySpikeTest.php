<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Tenancy\Support\GuardedWorkspaceDatabase;
use Tests\TestCase;

class TenancySpikeTest extends TestCase
{
    use RefreshDatabase;

    private GuardedWorkspaceDatabase $workspaceDatabases;

    private string $workspaceTemplateDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceDatabases = new GuardedWorkspaceDatabase;
        $this->workspaceTemplateDatabase = (string) config('database.connections.workspace.database');

        // Tenant connection inherits host/user/password from the workspace template.
        config([
            'database.connections.workspace.host' => config('database.connections.platform.host'),
            'database.connections.workspace.port' => config('database.connections.platform.port'),
            'database.connections.workspace.username' => config('database.connections.platform.username'),
            'database.connections.workspace.password' => config('database.connections.platform.password'),
        ]);
        DB::purge('workspace');

        SpikeWorkspaceProbeJob::reset();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->workspaceDatabases->dropTracked();

        parent::tearDown();
    }

    public function test_company_maps_to_stancl_tenant_and_workspace_database_name(): void
    {
        $company = Company::factory()->create();

        $this->assertSame($company->id, $company->getTenantKey());
        $this->assertSame('id', $company->getTenantKeyName());
        $this->assertSame($company->database_name, $company->database()->getName());
        $this->assertSame(
            'workspace_'.strtolower($company->id),
            $company->database()->getName()
        );
        $this->assertSame(Company::class, config('tenancy.tenant_model'));
    }

    public function test_platform_connection_stays_central_during_and_after_tenancy(): void
    {
        $company = Company::factory()->create();
        $this->workspaceDatabases->create($company->database_name);

        $platformName = 'laravelai_platform_testing';
        $workspaceTemplate = $this->workspaceTemplateDatabase;

        $this->assertSame($platformName, DB::connection('platform')->getDatabaseName());
        $this->assertSame('platform', config('database.default'));

        tenancy()->initialize($company);

        $this->assertTrue(tenancy()->initialized);
        $this->assertSame('tenant', config('database.default'));
        $this->assertSame($company->database_name, DB::connection()->getDatabaseName());
        $this->assertSame($platformName, DB::connection('platform')->getDatabaseName());
        $this->assertSame($platformName, config('database.connections.platform.database'));
        $this->assertSame($workspaceTemplate, config('database.connections.workspace.database'));

        tenancy()->end();

        $this->assertFalse(tenancy()->initialized);
        $this->assertSame('platform', config('database.default'));
        $this->assertSame($platformName, DB::connection('platform')->getDatabaseName());
        $this->assertSame($platformName, config('database.connections.platform.database'));
        $this->assertSame($workspaceTemplate, config('database.connections.workspace.database'));
    }

    public function test_platform_a_platform_b_platform_isolation_and_reset(): void
    {
        $companyA = Company::factory()->create(['slug' => 'spike-a']);
        $companyB = Company::factory()->create(['slug' => 'spike-b']);

        $this->workspaceDatabases->create($companyA->database_name);
        $this->workspaceDatabases->create($companyB->database_name);

        $this->assertSame('laravelai_platform_testing', DB::connection('platform')->getDatabaseName());
        $this->assertTrue(Schema::connection('platform')->hasTable('companies'));

        tenancy()->initialize($companyA);
        $this->createSpikeMarkerTable();
        DB::table('spike_markers')->insert(['token' => 'marker-a']);
        $this->assertSame($companyA->database_name, DB::connection()->getDatabaseName());
        $this->assertSame(1, DB::table('spike_markers')->where('token', 'marker-a')->count());
        $this->assertFalse(Schema::connection('tenant')->hasTable('companies'));
        $this->assertFalse(Schema::connection('platform')->hasTable('spike_markers'));
        tenancy()->end();

        $this->assertSame('platform', config('database.default'));
        $this->assertSame('laravelai_platform_testing', DB::connection('platform')->getDatabaseName());

        tenancy()->initialize($companyB);
        $this->createSpikeMarkerTable();
        DB::table('spike_markers')->insert(['token' => 'marker-b']);
        $this->assertSame($companyB->database_name, DB::connection()->getDatabaseName());
        $this->assertSame(0, DB::table('spike_markers')->where('token', 'marker-a')->count());
        $this->assertSame(1, DB::table('spike_markers')->where('token', 'marker-b')->count());
        $this->assertFalse(Schema::connection('tenant')->hasTable('companies'));
        tenancy()->end();

        $this->assertSame('platform', config('database.default'));

        tenancy()->initialize($companyA);
        $this->assertSame(1, DB::table('spike_markers')->where('token', 'marker-a')->count());
        $this->assertSame(0, DB::table('spike_markers')->where('token', 'marker-b')->count());
        tenancy()->end();

        $this->assertTrue(
            Company::query()->whereKey($companyA->id)->exists(),
            'Platform Company rows remain readable via platform connection after tenancy ends.'
        );
    }

    public function test_sync_queue_preserves_tenant_context_for_workspace_a_and_b(): void
    {
        $companyA = Company::factory()->create(['slug' => 'queue-a']);
        $companyB = Company::factory()->create(['slug' => 'queue-b']);

        $this->workspaceDatabases->create($companyA->database_name);
        $this->workspaceDatabases->create($companyB->database_name);

        tenancy()->initialize($companyA);
        SpikeWorkspaceProbeJob::reset();
        SpikeWorkspaceProbeJob::dispatchSync();
        $this->assertSame($companyA->database_name, SpikeWorkspaceProbeJob::$observedDatabase);
        $this->assertSame($companyA->id, SpikeWorkspaceProbeJob::$observedTenantKey);
        $this->assertFalse(SpikeWorkspaceProbeJob::$platformCompaniesVisible);
        tenancy()->end();

        $this->assertSame('laravelai_platform_testing', DB::connection('platform')->getDatabaseName());

        tenancy()->initialize($companyB);
        SpikeWorkspaceProbeJob::reset();
        SpikeWorkspaceProbeJob::dispatchSync();
        $this->assertSame($companyB->database_name, SpikeWorkspaceProbeJob::$observedDatabase);
        $this->assertSame($companyB->id, SpikeWorkspaceProbeJob::$observedTenantKey);
        $this->assertFalse(SpikeWorkspaceProbeJob::$platformCompaniesVisible);
        tenancy()->end();

        $this->assertSame('platform', config('database.default'));
        $this->assertSame('laravelai_platform_testing', DB::connection('platform')->getDatabaseName());
    }

    private function createSpikeMarkerTable(): void
    {
        Schema::connection('tenant')->dropIfExists('spike_markers');
        Schema::connection('tenant')->create('spike_markers', function ($table): void {
            $table->id();
            $table->string('token')->unique();
        });
    }
}
