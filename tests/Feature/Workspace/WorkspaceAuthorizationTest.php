<?php

namespace Tests\Feature\Workspace;

use App\Actions\Workspace\SeedWorkspaceRbacAction;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\PlatformUser;
use App\Models\WorkspacePermission;
use App\Models\WorkspaceRole;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * Workspace authorization via Gates (1G): the workspace-admin short-circuit, explicit
 * permission-slug gates, and the deny-by-default behavior — all inside TenantContext.
 */
class WorkspaceAuthorizationTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    /**
     * Seed baseline RBAC and return an onboarded workspace admin (holds workspace-admin).
     */
    private function makeAdmin(): WorkspaceUser
    {
        app(SeedWorkspaceRbacAction::class)->execute();

        $user = WorkspaceUser::create([
            'name' => 'Admin', 'email' => 'admin@acme.test', 'password' => 'secret-password',
        ]);
        $role = WorkspaceRole::where('slug', 'workspace-admin')->firstOrFail();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    public function test_workspace_admin_short_circuit_allows_any_ability(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $admin = $this->makeAdmin();

            // The admin passes the defined proof gates AND an arbitrary undefined ability,
            // demonstrating the system-role short-circuit (approved system authority).
            $this->assertTrue(Gate::forUser($admin)->allows('workspace.access'));
            $this->assertTrue(Gate::forUser($admin)->allows('workspace.manage'));
            $this->assertTrue(Gate::forUser($admin)->allows('something.not.defined.yet'));
        });
    }

    public function test_non_admin_user_only_passes_explicitly_granted_permissions(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            app(SeedWorkspaceRbacAction::class)->execute();

            $user = WorkspaceUser::create([
                'name' => 'Rep', 'email' => 'rep@acme.test', 'password' => 'secret-password',
            ]);
            $role = WorkspaceRole::create(['name' => 'Support', 'slug' => 'support']);
            $access = WorkspacePermission::where('slug', 'workspace.access')->firstOrFail();
            $role->permissions()->attach($access->id);
            $user->roles()->attach($role->id);

            $user = $user->fresh();

            // Explicitly granted → allowed; not granted → denied; arbitrary ability → denied.
            $this->assertTrue(Gate::forUser($user)->allows('workspace.access'));
            $this->assertFalse(Gate::forUser($user)->allows('workspace.manage'));
            $this->assertFalse(Gate::forUser($user)->allows('something.not.defined.yet'));
        });
    }

    public function test_user_with_no_roles_is_denied(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            app(SeedWorkspaceRbacAction::class)->execute();

            $user = WorkspaceUser::create([
                'name' => 'Nobody', 'email' => 'nobody@acme.test', 'password' => 'secret-password',
            ])->fresh();

            $this->assertFalse(Gate::forUser($user)->allows('workspace.access'));
            $this->assertFalse(Gate::forUser($user)->allows('workspace.manage'));
        });
    }

    public function test_permissions_are_isolated_between_companies(): void
    {
        $companyA = $this->provisionCompany('authz-a');
        $companyB = $this->provisionCompany('authz-b');

        // In A: a user granted workspace.access through a role.
        $companyA->run(function () {
            app(SeedWorkspaceRbacAction::class)->execute();
            $user = WorkspaceUser::create([
                'name' => 'A User', 'email' => 'user@shared.test', 'password' => 'secret-password',
            ]);
            $role = WorkspaceRole::create(['name' => 'Support', 'slug' => 'support']);
            $role->permissions()->attach(WorkspacePermission::where('slug', 'workspace.access')->firstOrFail()->id);
            $user->roles()->attach($role->id);

            $this->assertTrue(Gate::forUser($user->fresh())->allows('workspace.access'));
        });

        // In B: a same-email user with NO grant is denied — A's grant does not leak.
        $companyB->run(function () {
            app(SeedWorkspaceRbacAction::class)->execute();
            $user = WorkspaceUser::create([
                'name' => 'B User', 'email' => 'user@shared.test', 'password' => 'secret-password',
            ])->fresh();

            $this->assertFalse(Gate::forUser($user)->allows('workspace.access'));
        });
    }

    public function test_dashboard_route_requires_workspace_access_permission(): void
    {
        $company = $this->provisionCompany();
        Domain::factory()->create([
            'company_id' => $company->id,
            'domain' => 'authz.example.test',
            'status' => DomainStatus::Active,
            'created_by' => PlatformUser::factory(),
        ]);

        // Admin (seeded + granted via provisioning-style path) reaches the dashboard.
        $company->run(function () {
            $this->makeAdmin();
            // Mark onboarded so the forced-password-change middleware does not intercept.
            WorkspaceUser::where('email', 'admin@acme.test')->firstOrFail()
                ->forceFill(['must_change_password' => false])->save();
        });

        $admin = $company->run(fn () => WorkspaceUser::where('email', 'admin@acme.test')->firstOrFail());

        $this->actingAs($admin, 'workspace')
            ->get('http://authz.example.test/')
            ->assertOk();
    }
}
