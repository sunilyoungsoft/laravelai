<?php

namespace Tests\Feature\Workspace;

use App\Models\WorkspacePermission;
use App\Models\WorkspaceRole;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * Workspace RBAC models + relations, resolved inside and isolated to each tenant DB (1G).
 */
class WorkspaceRbacModelTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    public function test_role_and_permission_can_be_created_and_linked(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $role = WorkspaceRole::create(['name' => 'Sales', 'slug' => 'sales']);
            $permission = WorkspacePermission::create(['name' => 'View Customers', 'slug' => 'customers.view']);

            $role->permissions()->attach($permission->id);

            $this->assertTrue($role->permissions()->where('workspace_permissions.id', $permission->id)->exists());
            $this->assertTrue($permission->roles()->where('workspace_roles.id', $role->id)->exists());
        });
    }

    public function test_user_can_be_assigned_roles_and_resolves_permissions(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $user = WorkspaceUser::create([
                'name' => 'Sales Rep',
                'email' => 'rep@acme.test',
                'password' => 'secret-password',
            ]);

            $role = WorkspaceRole::create(['name' => 'Sales', 'slug' => 'sales']);
            $view = WorkspacePermission::create(['name' => 'View Customers', 'slug' => 'customers.view']);
            $create = WorkspacePermission::create(['name' => 'Create Customers', 'slug' => 'customers.create']);

            $role->permissions()->attach([$view->id, $create->id]);
            $user->roles()->attach($role->id);

            $user = $user->fresh();

            $this->assertTrue($user->hasRole('sales'));
            $this->assertFalse($user->hasRole('nonexistent'));
            $this->assertTrue($user->hasPermission('customers.view'));
            $this->assertTrue($user->hasPermission('customers.create'));
            $this->assertFalse($user->hasPermission('customers.delete'));
        });
    }

    public function test_inactive_role_does_not_grant_permissions(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $user = WorkspaceUser::create([
                'name' => 'Rep', 'email' => 'rep@acme.test', 'password' => 'secret-password',
            ]);
            $role = WorkspaceRole::create(['name' => 'Sales', 'slug' => 'sales']);
            $perm = WorkspacePermission::create(['name' => 'View', 'slug' => 'customers.view']);
            $role->permissions()->attach($perm->id);
            $user->roles()->attach($role->id);

            // Deactivate the role → permission no longer resolves.
            $role->forceFill(['status' => 0])->save();

            $this->assertFalse($user->fresh()->hasPermission('customers.view'));
            $this->assertFalse($user->fresh()->hasRole('sales'));
        });
    }

    public function test_is_workspace_admin_only_for_active_admin_system_role(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $user = WorkspaceUser::create([
                'name' => 'Admin', 'email' => 'admin@acme.test', 'password' => 'secret-password',
            ]);

            // A non-admin role does not make them a workspace admin.
            $ordinary = WorkspaceRole::create(['name' => 'Sales', 'slug' => 'sales']);
            $user->roles()->attach($ordinary->id);
            $this->assertFalse($user->fresh()->isWorkspaceAdmin());

            // The protected admin system role does.
            $admin = WorkspaceRole::factory()->admin()->create();
            $user->roles()->attach($admin->id);
            $this->assertTrue($user->fresh()->isWorkspaceAdmin());

            // An inactive admin role does not.
            $admin->forceFill(['status' => 0])->save();
            $this->assertFalse($user->fresh()->isWorkspaceAdmin());
        });
    }

    public function test_roles_and_permissions_are_isolated_between_companies(): void
    {
        $companyA = $this->provisionCompany('rbac-a');
        $companyB = $this->provisionCompany('rbac-b');

        $companyA->run(function () {
            WorkspaceRole::create(['name' => 'Sales', 'slug' => 'sales']);
            WorkspacePermission::create(['name' => 'View', 'slug' => 'customers.view']);
        });

        // The same slugs can exist independently in B, and A's rows are invisible here.
        $companyB->run(function () {
            $this->assertFalse(WorkspaceRole::query()->where('slug', 'sales')->exists());
            $this->assertFalse(WorkspacePermission::query()->where('slug', 'customers.view')->exists());

            WorkspaceRole::create(['name' => 'Sales', 'slug' => 'sales']);
            $this->assertSame(1, WorkspaceRole::query()->count());
        });

        $companyA->run(function () {
            $this->assertSame(1, WorkspaceRole::query()->count());
        });
    }
}
