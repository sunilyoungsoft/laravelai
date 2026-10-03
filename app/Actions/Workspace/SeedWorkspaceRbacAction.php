<?php

namespace App\Actions\Workspace;

use App\Models\WorkspacePermission;
use App\Models\WorkspaceRole;

/**
 * Seed the baseline Workspace RBAC for a single tenant (1G).
 *
 * MUST run inside tenant context (the caller enters it, e.g. via $company->run(...)),
 * because the RBAC models resolve on the dynamic `tenant` connection.
 *
 * Seeds:
 *  - the protected `workspace-admin` system role (is_system=true, status=1),
 *  - the Phase 1G proof permissions `workspace.access` and `workspace.manage`,
 *  - and assigns both permissions to the admin role explicitly (no wildcards).
 *
 * Idempotent and soft-delete-safe (mirrors PlatformRoleSeeder): rows are matched by slug
 * withTrashed, restored if soft-deleted, and their system invariants re-enforced. Running it
 * repeatedly never creates duplicates and never drops data — safe for both new provisioning
 * and existing workspaces via `workspace:seed-rbac`.
 */
class SeedWorkspaceRbacAction
{
    /**
     * The baseline permissions seeded in Phase 1G. Business-module permissions are added by
     * each module later (same pattern): register the permission here/in the module seeder and
     * assign it to the admin role explicitly.
     *
     * @var array<string, string> slug => human name
     */
    private const PERMISSIONS = [
        'workspace.access' => 'Access Workspace',
        'workspace.manage' => 'Manage Workspace',
    ];

    public function execute(): void
    {
        $adminRole = $this->ensureAdminSystemRole();

        $permissionIds = [];
        foreach (self::PERMISSIONS as $slug => $name) {
            $permissionIds[] = $this->ensurePermission($slug, $name)->id;
        }

        // Assign every baseline permission to the admin role explicitly. syncWithoutDetaching
        // is idempotent and never removes grants a module may have added.
        $adminRole->permissions()->syncWithoutDetaching($permissionIds);
    }

    /**
     * Create or restore the protected `workspace-admin` system role and enforce its invariants.
     */
    private function ensureAdminSystemRole(): WorkspaceRole
    {
        $role = WorkspaceRole::withTrashed()->where('slug', 'workspace-admin')->first();

        if ($role === null) {
            $role = new WorkspaceRole;
            $role->forceFill([
                'name' => 'Workspace Admin',
                'slug' => 'workspace-admin',
                'description' => 'Protected workspace system administrator role.',
                'is_system' => true,
                'status' => 1,
            ])->save();

            return $role;
        }

        if ($role->trashed()) {
            $role->restore();
        }

        $role->forceFill([
            'name' => 'Workspace Admin',
            'slug' => 'workspace-admin',
            'is_system' => true,
            'status' => 1,
        ])->save();

        return $role;
    }

    /**
     * Create or restore a permission by slug and enforce its name/active invariants.
     */
    private function ensurePermission(string $slug, string $name): WorkspacePermission
    {
        $permission = WorkspacePermission::withTrashed()->where('slug', $slug)->first();

        if ($permission === null) {
            $permission = new WorkspacePermission;
            $permission->forceFill([
                'name' => $name,
                'slug' => $slug,
                'status' => 1,
            ])->save();

            return $permission;
        }

        if ($permission->trashed()) {
            $permission->restore();
        }

        $permission->forceFill([
            'name' => $name,
            'slug' => $slug,
            'status' => 1,
        ])->save();

        return $permission;
    }
}
