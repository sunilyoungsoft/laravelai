<?php

namespace Tests\Feature\Workspace;

use App\Actions\Workspace\MigrateWorkspaceAction;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * workspace:migrate applies pending tenant migrations to an existing active workspace
 * without dropping or recreating it, preserving tenant data (1F-G).
 *
 * Simulates a workspace provisioned before a new tenant migration existed by dropping the
 * workspace_users table (and recording seed data elsewhere), then migrating in place.
 */
class MigrateWorkspaceTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    private function action(): MigrateWorkspaceAction
    {
        return app(MigrateWorkspaceAction::class);
    }

    /**
     * Put the workspace in a "pre-workspace_users" state with some existing tenant data.
     */
    private function regressWorkspace(Company $company): void
    {
        $company->run(function () {
            // Fabricate a "pre-workspace_users" tenant schema. workspace_users is now the
            // target of RBAC pivot FKs (workspace_user_roles), so drop the dependents first
            // with FK checks disabled — this is a test-only simulation of an older schema
            // state, not production behavior.
            Schema::connection('tenant')->disableForeignKeyConstraints();
            Schema::connection('tenant')->dropIfExists('workspace_role_permissions');
            Schema::connection('tenant')->dropIfExists('workspace_user_roles');
            Schema::connection('tenant')->dropIfExists('workspace_permissions');
            Schema::connection('tenant')->dropIfExists('workspace_roles');
            Schema::connection('tenant')->dropIfExists('workspace_users');
            Schema::connection('tenant')->enableForeignKeyConstraints();

            // Remove the corresponding migration ledger rows so they are pending again.
            DB::connection('tenant')->table('migrations')
                ->where('migration', 'like', '%create_workspace_users_table')
                ->orWhere('migration', 'like', '%create_workspace_roles_table')
                ->orWhere('migration', 'like', '%create_workspace_permissions_table')
                ->orWhere('migration', 'like', '%create_workspace_user_roles_table')
                ->orWhere('migration', 'like', '%create_workspace_role_permissions_table')
                ->delete();

            // Existing business data that must survive the in-place migrate.
            DB::connection('tenant')->table('workspace_meta')->insert([
                'id' => (string) Str::ulid(),
                'key' => 'seed',
                'value' => 'keep-me',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_migrate_adds_workspace_users_to_an_existing_workspace_preserving_data(): void
    {
        $company = $this->provisionCompany();
        $this->regressWorkspace($company);

        // Precondition: workspace_users is gone, seed data present.
        $company->run(function () {
            $this->assertFalse(Schema::connection('tenant')->hasTable('workspace_users'));
            $this->assertSame('keep-me', DB::connection('tenant')->table('workspace_meta')->where('key', 'seed')->value('value'));
        });

        $this->action()->execute($company);

        $company->run(function () {
            // workspace_users recreated...
            $this->assertTrue(Schema::connection('tenant')->hasTable('workspace_users'));
            // ...and the pre-existing tenant data was NOT dropped.
            $this->assertSame('keep-me', DB::connection('tenant')->table('workspace_meta')->where('key', 'seed')->value('value'));
        });
    }

    public function test_migrate_is_idempotent(): void
    {
        $company = $this->provisionCompany();

        // A freshly provisioned workspace already has everything; migrate is a no-op.
        $this->action()->execute($company);
        $this->action()->execute($company);

        $company->run(function () {
            $this->assertTrue(Schema::connection('tenant')->hasTable('workspace_users'));
        });
    }

    public function test_migrate_refuses_a_non_active_company(): void
    {
        $company = Company::factory()->create(); // pending, no workspace DB

        $this->expectException(RuntimeException::class);

        $this->action()->execute($company);
    }

    public function test_command_migrates_a_single_company_by_slug(): void
    {
        $company = $this->provisionCompany('acme-co');
        $this->regressWorkspace($company);

        $this->artisan('workspace:migrate', ['company' => 'acme-co'])
            ->assertExitCode(0);

        $company->run(function () {
            $this->assertTrue(Schema::connection('tenant')->hasTable('workspace_users'));
        });
    }

    public function test_command_migrates_all_active_companies(): void
    {
        $a = $this->provisionCompany('alpha-co');
        $b = $this->provisionCompany('beta-co');
        $this->regressWorkspace($a);
        $this->regressWorkspace($b);

        $this->artisan('workspace:migrate', ['--all' => true])
            ->assertExitCode(0);

        foreach ([$a, $b] as $company) {
            $company->run(function () {
                $this->assertTrue(Schema::connection('tenant')->hasTable('workspace_users'));
            });
        }
    }

    public function test_command_rejects_unknown_company(): void
    {
        $this->artisan('workspace:migrate', ['company' => 'does-not-exist'])
            ->assertExitCode(1);
    }
}
