<?php

namespace Tests\Feature\Provisioning;

use App\Actions\Platform\ProvisionWorkspaceAction;
use App\Enums\CompanyStatus;
use App\Exceptions\Provisioning\CompanyNotProvisionableException;
use App\Exceptions\Provisioning\WorkspaceProvisioningException;
use App\Models\Company;
use App\Services\Platform\WorkspaceDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Feature\Tenancy\Support\GuardedWorkspaceDatabase;
use Tests\TestCase;

class ProvisionWorkspaceActionTest extends TestCase
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

    private function action(): ProvisionWorkspaceAction
    {
        return app(ProvisionWorkspaceAction::class);
    }

    private function pendingCompany(): Company
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Pending]);
        $this->workspaceDatabases->trackExisting($company->database_name);

        return $company;
    }

    public function test_successful_provisioning_creates_migrates_and_activates(): void
    {
        $company = $this->pendingCompany();

        $result = $this->action()->execute($company);

        $this->assertSame(CompanyStatus::Active, $result->status);
        $this->assertSame(CompanyStatus::Active, $company->fresh()->status);

        $company->run(function (): void {
            $this->assertTrue(Schema::hasTable('workspace_meta'));
        });
    }

    public function test_company_becomes_active_only_after_provisioning(): void
    {
        $company = $this->pendingCompany();
        $this->assertNotSame(CompanyStatus::Active, $company->status);

        $this->action()->execute($company);

        $this->assertSame(CompanyStatus::Active, $company->fresh()->status);
    }

    public function test_failed_database_creation_marks_provisioning_failed(): void
    {
        $company = $this->pendingCompany();

        $service = new class extends WorkspaceDatabaseService
        {
            public function recreateDatabase(Company $company): void
            {
                throw new RuntimeException('simulated CREATE DATABASE failure');
            }
        };

        $action = new ProvisionWorkspaceAction($service);

        try {
            $action->execute($company);
            $this->fail('Expected provisioning to throw.');
        } catch (WorkspaceProvisioningException) {
            // expected
        }

        $this->assertSame(CompanyStatus::ProvisioningFailed, $company->fresh()->status);
    }

    public function test_failed_migration_marks_provisioning_failed_and_never_active(): void
    {
        $company = $this->pendingCompany();

        $service = new class extends WorkspaceDatabaseService
        {
            public function runWorkspaceMigrations(Company $company): void
            {
                throw new RuntimeException('simulated migration failure');
            }
        };

        $action = new ProvisionWorkspaceAction($service);

        try {
            $action->execute($company);
            $this->fail('Expected provisioning to throw.');
        } catch (WorkspaceProvisioningException) {
            // expected
        }

        $this->assertSame(CompanyStatus::ProvisioningFailed, $company->fresh()->status);
    }

    public function test_retry_from_failed_deletes_and_recreates_then_activates(): void
    {
        $company = $this->pendingCompany();

        // First attempt fails after DB creation (migration blows up), leaving a partial DB.
        $failing = new class extends WorkspaceDatabaseService
        {
            public function runWorkspaceMigrations(Company $company): void
            {
                throw new RuntimeException('first attempt migration failure');
            }
        };

        try {
            (new ProvisionWorkspaceAction($failing))->execute($company);
        } catch (WorkspaceProvisioningException) {
            // expected
        }

        $this->assertSame(CompanyStatus::ProvisioningFailed, $company->fresh()->status);
        // Partial DB exists after the failed attempt.
        $this->assertTrue((new WorkspaceDatabaseService)->databaseExists($company->fresh()));

        // Retry with the real service: should delete+recreate and reach active.
        $result = $this->action()->execute($company->fresh());

        $this->assertSame(CompanyStatus::Active, $result->status);
        $company->fresh()->run(function (): void {
            $this->assertTrue(Schema::hasTable('workspace_meta'));
        });
    }

    public function test_active_company_provisioning_is_rejected(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->expectException(CompanyNotProvisionableException::class);

        try {
            $this->action()->execute($company);
        } finally {
            // status unchanged
            $this->assertSame(CompanyStatus::Active, $company->fresh()->status);
        }
    }

    public function test_provisioning_company_refuses_concurrent_run(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Provisioning]);

        $this->expectException(CompanyNotProvisionableException::class);

        try {
            $this->action()->execute($company);
        } finally {
            $this->assertSame(CompanyStatus::Provisioning, $company->fresh()->status);
        }
    }

    public function test_suspended_company_is_refused(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Suspended]);

        $this->expectException(CompanyNotProvisionableException::class);

        try {
            $this->action()->execute($company);
        } finally {
            $this->assertSame(CompanyStatus::Suspended, $company->fresh()->status);
        }
    }

    public function test_deactivated_company_is_refused(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Deactivated]);

        $this->expectException(CompanyNotProvisionableException::class);

        try {
            $this->action()->execute($company);
        } finally {
            $this->assertSame(CompanyStatus::Deactivated, $company->fresh()->status);
        }
    }

    public function test_workspace_database_identity_matches_company_database_name(): void
    {
        $company = $this->pendingCompany();

        $this->action()->execute($company);

        $company->fresh()->run(function () use ($company): void {
            $this->assertSame($company->database_name, DB::connection()->getDatabaseName());
        });
    }

    public function test_platform_database_unaffected_by_provisioning(): void
    {
        $company = $this->pendingCompany();

        $this->action()->execute($company);

        $this->assertSame('laravelai_platform_testing', DB::connection('platform')->getDatabaseName());
        $this->assertSame('platform', config('database.default'));
    }
}
