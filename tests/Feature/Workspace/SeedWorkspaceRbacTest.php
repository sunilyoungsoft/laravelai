<?php

namespace Tests\Feature\Workspace;

use App\Actions\Workspace\CreateInitialWorkspaceAdminAction;
use App\Actions\Workspace\CreateInitialWorkspaceAdminData;
use App\Actions\Workspace\SeedWorkspaceRbacAction;
use App\Models\WorkspacePermission;
use App\Models\WorkspaceRole;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * Baseline Workspace RBAC seeding (1G): the workspace-admin system role + proof permissions
 * are created idempotently, assigned explicitly, and the initial admin receives the role.
 */
class SeedWorkspaceRbacTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    private function rbacSeeder(): SeedWorkspaceRbacAction
    {
        return app(SeedWorkspaceRbacAction::class);
    }

    public function test_seeder_creates_admin_role_and_proof_permissions_assigned_to_role(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $this->rbacSeeder()->execute();

            $role = WorkspaceRole::where('slug', 'workspace-admin')->firstOrFail();
            $this->assertTrue($role->is_system);
            $this->assertSame(1, $role->status);

            foreach (['workspace.access', 'workspace.manage'] as $slug) {
                $perm = WorkspacePermission::where('slug', $slug)->firstOrFail();
                $this->assertSame(1, $perm->status);
                $this->assertTrue(
                    $role->permissions()->where('workspace_permissions.id', $perm->id)->exists(),
                    "{$slug} must be assigned to the workspace-admin role."
                );
            }
        });
    }

    public function test_seeder_is_idempotent_and_creates_no_duplicates(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $this->rbacSeeder()->execute();
            $this->rbacSeeder()->execute();
            $this->rbacSeeder()->execute();

            $this->assertSame(1, WorkspaceRole::where('slug', 'workspace-admin')->count());
            $this->assertSame(1, WorkspacePermission::where('slug', 'workspace.access')->count());
            $this->assertSame(1, WorkspacePermission::where('slug', 'workspace.manage')->count());

            // The admin role has exactly the two proof permissions (no duplicate pivot rows).
            $role = WorkspaceRole::where('slug', 'workspace-admin')->firstOrFail();
            $this->assertSame(2, $role->permissions()->count());
        });
    }

    public function test_seeder_restores_a_soft_deleted_admin_role_rather_than_duplicating(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $this->rbacSeeder()->execute();
            WorkspaceRole::where('slug', 'workspace-admin')->firstOrFail()->delete();

            $this->rbacSeeder()->execute();

            $this->assertSame(1, WorkspaceRole::withTrashed()->where('slug', 'workspace-admin')->count());
            $this->assertFalse(WorkspaceRole::where('slug', 'workspace-admin')->firstOrFail()->trashed());
        });
    }

    public function test_provisioning_initial_admin_receives_the_workspace_admin_role(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            app(CreateInitialWorkspaceAdminAction::class)->execute(
                new CreateInitialWorkspaceAdminData('Acme Admin', 'admin@acme.test')
            );

            $user = WorkspaceUser::where('email', 'admin@acme.test')->firstOrFail();

            $this->assertTrue($user->isWorkspaceAdmin());
            $this->assertTrue($user->hasRole('workspace-admin'));
            // Via the admin role, the proof permissions resolve.
            $this->assertTrue($user->hasPermission('workspace.access'));
            $this->assertTrue($user->hasPermission('workspace.manage'));
        });
    }

    public function test_seed_rbac_command_seeds_an_existing_workspace_by_slug(): void
    {
        $company = $this->provisionCompany('rbac-cmd');

        // Simulate a pre-1G workspace: wipe the seeded RBAC rows (keep tables).
        $company->run(function () {
            WorkspaceRole::query()->forceDelete();
            WorkspacePermission::query()->forceDelete();
        });

        $this->artisan('workspace:seed-rbac', ['company' => 'rbac-cmd'])->assertExitCode(0);

        $company->run(function () {
            $this->assertTrue(WorkspaceRole::where('slug', 'workspace-admin')->exists());
            $this->assertSame(2, WorkspacePermission::query()->count());
        });
    }

    public function test_seed_rbac_command_rejects_unknown_company(): void
    {
        $this->artisan('workspace:seed-rbac', ['company' => 'does-not-exist'])->assertExitCode(1);
    }

    public function test_seeding_preserves_existing_tenant_data(): void
    {
        $company = $this->provisionCompany('rbac-preserve');

        // Pre-existing workspace user that must survive seeding.
        $company->run(function () {
            WorkspaceUser::create([
                'name' => 'Existing', 'email' => 'existing@acme.test', 'password' => 'secret-password',
            ]);
        });

        $this->artisan('workspace:seed-rbac', ['company' => 'rbac-preserve'])->assertExitCode(0);

        $company->run(function () {
            $this->assertTrue(WorkspaceUser::where('email', 'existing@acme.test')->exists());
            $this->assertTrue(WorkspaceRole::where('slug', 'workspace-admin')->exists());
        });
    }
}
