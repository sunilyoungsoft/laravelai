<?php

namespace Tests\Feature\Provisioning;

use App\Actions\Platform\ProvisionWorkspaceAction;
use App\Enums\CompanyStatus;
use App\Jobs\Platform\ProvisionWorkspaceJob;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Feature\Tenancy\Support\GuardedWorkspaceDatabase;
use Tests\TestCase;

class ProvisionWorkspaceEntryPointsTest extends TestCase
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

    public function test_command_provisions_via_the_action_and_reaches_active(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Pending, 'slug' => 'cmd-co']);
        $this->workspaceDatabases->trackExisting($company->database_name);

        $this->artisan('workspace:provision', ['company' => $company->slug])
            ->assertSuccessful();

        $this->assertSame(CompanyStatus::Active, $company->fresh()->status);
    }

    public function test_command_resolves_company_by_id(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Pending]);
        $this->workspaceDatabases->trackExisting($company->database_name);

        $this->artisan('workspace:provision', ['company' => $company->id])
            ->assertSuccessful();

        $this->assertSame(CompanyStatus::Active, $company->fresh()->status);
    }

    public function test_command_fails_for_unknown_company(): void
    {
        $this->artisan('workspace:provision', ['company' => 'does-not-exist'])
            ->assertFailed();
    }

    public function test_command_calls_the_shared_action(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Pending]);

        $mock = Mockery::mock(ProvisionWorkspaceAction::class);
        $mock->shouldReceive('execute')
            ->once()
            ->andReturnUsing(fn (Company $c) => $c);
        $this->app->instance(ProvisionWorkspaceAction::class, $mock);

        $this->artisan('workspace:provision', ['company' => $company->id])
            ->assertSuccessful();
    }

    public function test_job_provisions_via_the_action_and_reaches_active(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Pending, 'slug' => 'job-co']);
        $this->workspaceDatabases->trackExisting($company->database_name);

        // Sync queue (phpunit QUEUE_CONNECTION=sync) runs the job inline.
        ProvisionWorkspaceJob::dispatch($company->id);

        $this->assertSame(CompanyStatus::Active, $company->fresh()->status);
    }

    public function test_job_resolves_company_on_platform_and_calls_shared_action(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Pending]);

        $mock = Mockery::mock(ProvisionWorkspaceAction::class);
        $mock->shouldReceive('execute')
            ->once()
            ->withArgs(fn (Company $c) => $c->id === $company->id)
            ->andReturnUsing(fn (Company $c) => $c);
        $this->app->instance(ProvisionWorkspaceAction::class, $mock);

        ProvisionWorkspaceJob::dispatchSync($company->id);
    }

    public function test_job_throws_for_unknown_company(): void
    {
        $this->expectException(\RuntimeException::class);

        ProvisionWorkspaceJob::dispatchSync('unknown-company-id');
    }
}
