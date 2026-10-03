<?php

namespace Tests\Security;

use App\Actions\Workspace\SeedWorkspaceRbacAction;
use App\Models\Company;
use App\Models\PlatformRole;
use App\Models\PlatformUser;
use App\Models\WorkspaceRole;
use App\Models\WorkspaceUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * Platform RBAC and Workspace RBAC are completely separate authorization systems (1G).
 *
 * Negative security matrix:
 *  - A PlatformUser (even a Platform Admin) can never satisfy a Workspace gate.
 *  - A WorkspaceUser (even a Workspace Admin) can never satisfy a Platform ability
 *    (CompanyPolicy), and the workspace-admin Gate::before short-circuit must not leak into
 *    Platform authorization.
 *  - Missing tenant context fails safely (no workspace grant).
 */
class WorkspacePlatformAuthorizationSeparationTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    private function platformAdmin(): PlatformUser
    {
        $user = PlatformUser::factory()->create();
        $role = PlatformRole::factory()->create([
            'name' => 'Admin', 'slug' => 'admin', 'is_system' => true, 'status' => 1,
        ]);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    public function test_platform_user_cannot_satisfy_workspace_gates(): void
    {
        $company = $this->provisionCompany();
        $platformAdmin = $this->platformAdmin();

        // Even inside tenant context, a PlatformUser is not a WorkspaceUser → denied.
        $company->run(function () use ($platformAdmin) {
            app(SeedWorkspaceRbacAction::class)->execute();

            $this->assertFalse(Gate::forUser($platformAdmin)->allows('workspace.access'));
            $this->assertFalse(Gate::forUser($platformAdmin)->allows('workspace.manage'));
        });
    }

    public function test_workspace_admin_cannot_satisfy_platform_abilities(): void
    {
        $company = $this->provisionCompany();

        // A target Platform company (platform connection) for the CompanyPolicy abilities.
        $target = Company::factory()->create();

        // Evaluate inside tenant context — the realistic situation in which a workspace admin
        // exists and is authorized. Even as a bona fide workspace-admin (short-circuit active),
        // the user must NOT be able to obtain any Platform CompanyPolicy grant.
        $company->run(function () use ($target) {
            app(SeedWorkspaceRbacAction::class)->execute();
            $workspaceAdmin = WorkspaceUser::create([
                'name' => 'WS Admin', 'email' => 'wsadmin@acme.test', 'password' => 'secret-password',
            ]);
            $workspaceAdmin->roles()->attach(WorkspaceRole::where('slug', 'workspace-admin')->firstOrFail()->id);
            $workspaceAdmin = $workspaceAdmin->fresh();

            // Sanity: the short-circuit genuinely applies to WORKSPACE abilities (bare,
            // model-less gates).
            $this->assertTrue(Gate::forUser($workspaceAdmin)->allows('workspace.manage'));

            // The workspace Gate::before only fires for model-less workspace abilities, so it
            // never short-circuits a Platform (CompanyPolicy) ability — those always target a
            // Platform model. The short-circuit therefore does NOT return true for any Platform
            // ability, and Platform authorization stays enforced by CompanyPolicy, which only
            // accepts a PlatformUser. A WorkspaceUser can never obtain a Platform grant.
            foreach (['viewAny' => Company::class, 'view' => $target, 'provision' => $target] as $ability => $argument) {
                $granted = true;

                try {
                    $granted = Gate::forUser($workspaceAdmin)->allows($ability, $argument);
                } catch (\TypeError $e) {
                    // CompanyPolicy structurally refuses a non-PlatformUser — a stronger
                    // guarantee than a runtime false: the Platform policy will not even accept
                    // a workspace identity.
                    $this->assertStringContainsString(PlatformUser::class, $e->getMessage());
                    $granted = false;
                }

                $this->assertFalse($granted, "Workspace admin must never satisfy Platform ability [{$ability}].");
            }
        });
    }

    public function test_platform_admin_authorization_is_unaffected_by_workspace_gate_before(): void
    {
        // Sanity: the workspace Gate::before returns null for non-WorkspaceUsers, so the
        // Platform admin still passes CompanyPolicy exactly as before 1G.
        $platformAdmin = $this->platformAdmin();
        $target = Company::factory()->create();

        $this->assertTrue(Gate::forUser($platformAdmin)->allows('viewAny', Company::class));
        $this->assertTrue(Gate::forUser($platformAdmin)->allows('provision', $target));
    }

    public function test_workspace_permission_resolution_requires_the_tenant_database(): void
    {
        // Workspace authorization data lives only in the tenant database. Outside tenant
        // context there is no `tenant` connection, so resolving a workspace permission cannot
        // succeed — it raises a connection error rather than silently reading another
        // database. This proves there is no cross-DB fallback that could grant access.
        //
        // (The HTTP-level fail-safe — central/unknown hosts exposing no workspace auth — is
        // covered end-to-end by Tests\Security\WorkspaceAuthIsolationTest.)
        $this->assertFalse(tenancy()->initialized);

        $user = new WorkspaceUser;
        $user->forceFill(['id' => (string) Str::ulid()]);
        $user->exists = true;

        // Outside tenant context WorkspaceUser falls back to the default `platform`
        // connection, which has NO workspace_* tables — so the lookup errors instead of
        // silently reading another database. This proves the critical property: there is no
        // cross-DB fallback, so a missing tenant context can never grant a workspace
        // permission (Platform RBAC and Workspace RBAC never share storage).
        $this->expectException(QueryException::class);

        $user->hasPermission('workspace.access');
    }
}
