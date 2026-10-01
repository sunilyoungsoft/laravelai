<?php

namespace Tests\Feature\Provisioning;

use App\Models\Company;
use App\Services\Platform\WorkspaceDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Feature\Tenancy\Support\GuardedWorkspaceDatabase;
use Tests\TestCase;

class WorkspaceDatabaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private GuardedWorkspaceDatabase $workspaceDatabases;

    private WorkspaceDatabaseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceDatabases = new GuardedWorkspaceDatabase;

        // Tenant connection inherits host/user/password from the platform connection.
        config([
            'database.connections.workspace.host' => config('database.connections.platform.host'),
            'database.connections.workspace.port' => config('database.connections.platform.port'),
            'database.connections.workspace.username' => config('database.connections.platform.username'),
            'database.connections.workspace.password' => config('database.connections.platform.password'),
        ]);
        DB::purge('workspace');

        $this->service = new WorkspaceDatabaseService;
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
     * Track a database created by the service so tearDown drops it.
     */
    private function track(Company $company): void
    {
        // GuardedWorkspaceDatabase tracks names it created; register the service-created name
        // by creating-if-not-exists is unsafe, so we instead drop via a fresh guard call.
        $this->workspaceDatabases->trackExisting($company->database_name);
    }

    public function test_create_then_exists_is_true_and_platform_untouched(): void
    {
        $company = Company::factory()->create();
        $this->track($company);

        $this->assertFalse($this->service->databaseExists($company));

        $this->service->createDatabase($company);

        $this->assertTrue($this->service->databaseExists($company));

        // Platform connection is unaffected.
        $this->assertSame('laravelai_platform_testing', DB::connection('platform')->getDatabaseName());
        $this->assertTrue(Schema::connection('platform')->hasTable('companies'));
    }

    public function test_run_workspace_migrations_leaves_no_pending_and_creates_tenant_table(): void
    {
        $company = Company::factory()->create();
        $this->track($company);

        $this->service->createDatabase($company);
        $this->service->runWorkspaceMigrations($company);

        $this->assertSame(0, $this->service->pendingMigrationCount($company));

        $company->run(function () use ($company): void {
            $this->assertSame($company->database_name, DB::connection()->getDatabaseName());
            $this->assertTrue(Schema::hasTable('workspace_meta'));
            $this->assertFalse(Schema::hasTable('companies'), 'Workspace must not contain platform tables.');
        });

        // Platform never grew a workspace_meta table.
        $this->assertFalse(Schema::connection('platform')->hasTable('workspace_meta'));
    }

    public function test_recreate_drops_and_recreates_fresh_database(): void
    {
        $company = Company::factory()->create();
        $this->track($company);

        $this->service->createDatabase($company);
        $this->service->runWorkspaceMigrations($company);

        // Put a marker row in the tenant DB.
        $company->run(function (): void {
            DB::table('workspace_meta')->insert([
                'id' => (string) Str::ulid(),
                'key' => 'marker',
                'value' => 'before-recreate',
            ]);
        });

        $this->service->recreateDatabase($company);

        // Fresh DB: no tables migrated yet, so pending > 0 and marker gone once migrated.
        $this->assertGreaterThan(0, $this->service->pendingMigrationCount($company));

        $this->service->runWorkspaceMigrations($company);
        $company->run(function (): void {
            $this->assertSame(0, DB::table('workspace_meta')->where('key', 'marker')->count());
        });
    }

    public function test_drop_database_refuses_non_workspace_name(): void
    {
        $company = Company::factory()->make();
        $company->id = 'not-a-ulid';
        $company->database_name = 'laravelai_platform_testing';

        $this->expectException(RuntimeException::class);
        $this->service->dropDatabase($company);

        // Platform database still present.
        $this->assertSame('laravelai_platform_testing', DB::connection('platform')->getDatabaseName());
    }

    public function test_database_name_matches_company_database_name(): void
    {
        $company = Company::factory()->create();
        $this->track($company);

        $this->service->createDatabase($company);

        $company->run(function () use ($company): void {
            $this->assertSame($company->database_name, DB::connection()->getDatabaseName());
            $this->assertSame('workspace_'.strtolower($company->id), DB::connection()->getDatabaseName());
        });
    }
}
