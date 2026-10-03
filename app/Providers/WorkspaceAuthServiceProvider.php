<?php

namespace App\Providers;

use App\Models\WorkspaceUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Workspace (tenant) authorization wiring (1G).
 *
 * Kept deliberately separate from Platform authorization (AppServiceProvider wires the
 * Platform CompanyPolicy). This provider only governs the `workspace` guard's abilities and
 * never touches Platform gates/policies.
 *
 * Design:
 *  - A Gate::before short-circuit grants every WORKSPACE ability to a WorkspaceUser holding
 *    the active `workspace-admin` system role. This is system-role authority (approved), NOT
 *    a wildcard permission record. It is scoped strictly in two ways: it only ever acts on a
 *    WorkspaceUser, and it only fires for workspace abilities — bare, slug-style abilities
 *    with no model/class argument. Platform authorization is Policy-backed and always targets
 *    a Platform model (e.g. Company), so the short-circuit never fires for it. The before
 *    callback returns null (not false) for everyone/everything else so normal gates/policies
 *    still run — a PlatformUser, and Platform abilities, are completely unaffected.
 *  - Permission-slug gates (workspace.access, workspace.manage) resolve through
 *    WorkspaceUser::hasPermission, which reads only the tenant database. A non-WorkspaceUser
 *    (e.g. a PlatformUser) can never satisfy a workspace gate.
 *
 * All checks are meaningful only inside a resolved TenantContext (behind tenant.resolve),
 * where WorkspaceUser resolves on the dynamic `tenant` connection.
 */
class WorkspaceAuthServiceProvider extends ServiceProvider
{
    /**
     * The Workspace permission slugs exposed as Gates in Phase 1G. Business-module
     * permissions are added by each module later (same pattern) and need not be registered
     * here individually — a module may define its own gates or reuse hasPermission directly.
     *
     * @var list<string>
     */
    private const PERMISSION_GATES = [
        'workspace.access',
        'workspace.manage',
    ];

    public function boot(): void
    {
        // System-role short-circuit — strictly scoped to the Workspace Admin system role AND
        // to workspace abilities only. A workspace ability is a bare, slug-style gate with no
        // model/class argument. Platform abilities (CompanyPolicy) are Policy-backed and
        // always target a Platform model, so the short-circuit never fires for them: a
        // workspace admin can never satisfy a Platform ability. Returning null (never false)
        // lets all other authorization proceed unchanged, so Platform authorization and
        // non-admin workspace checks are unaffected.
        Gate::before(function (?Authenticatable $user, string $ability, array $arguments = []): ?bool {
            if ($arguments !== []) {
                return null;
            }

            if ($user instanceof WorkspaceUser && $user->isWorkspaceAdmin()) {
                return true;
            }

            return null;
        });

        // Explicit permission-slug gates. Only a WorkspaceUser holding the permission (via an
        // active role, in this tenant's database) passes; anyone else is denied.
        foreach (self::PERMISSION_GATES as $slug) {
            Gate::define($slug, function (?Authenticatable $user) use ($slug): bool {
                return $user instanceof WorkspaceUser && $user->hasPermission($slug);
            });
        }
    }
}
