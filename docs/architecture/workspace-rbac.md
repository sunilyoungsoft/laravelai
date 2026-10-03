# Workspace RBAC & Authorization (Phase 1G)

How authorization works **inside a workspace (tenant)**, and the pattern every future
business module must follow to add its own permissions. Platform RBAC is a separate system —
see `docs/database/platform.dbml` and `CompanyPolicy` for that side.

> Golden rule: **Platform RBAC and Workspace RBAC never share storage and never grant each
> other.** Workspace roles/permissions live only in each Company's workspace database; a
> `PlatformUser` can never satisfy a workspace ability, and a `WorkspaceUser` can never satisfy
> a Platform ability.

## Identities & authentication realms (product decision)

**Platform Admin** and **Workspace Super Admin** are two separate identities in two separate
authentication realms. They are not the same account with different scopes — they are different
users, in different databases, authenticated by different guards.

| | Platform Admin | Workspace Super Admin |
| --- | --- | --- |
| Lives in | Platform DB (`platform_users`) | that Company's workspace DB (`workspace_users`) |
| Guard | `platform` | `workspace` |
| Governs | the SaaS platform + Company **lifecycle** (create/provision/suspend companies, domains, plans) | everything **inside one Workspace** |
| Authority | Platform management only | highest-privileged user **within their own Workspace** |

Key rules:

- **Platform Admin manages the SaaS platform and the Company lifecycle** — it does not operate a
  Company's business data.
- **The initial Workspace Super Admin is created inside the Workspace at provisioning time.**
  This is the root administrative identity for that Workspace and remains so.
- The Workspace Super Admin has **unrestricted authority within their own Workspace** and is the
  highest-privileged Workspace user. Over time this covers all Workspace-level functionality:
  users, roles/permissions, modules, workspace settings, billing/subscription configuration, API
  keys, integrations, and business operations.
- **A Platform Admin is NOT automatically a Workspace user.** Managing a Company at the Platform
  level must never grant implicit access to that Company's Workspace business data. The two
  identities stay isolated by design (and are enforced — see the authorization and separation
  sections below).
- **Any future Platform→Workspace administrative/support access** (e.g. "log in as a tenant for
  support") must be designed as an **explicit, separate feature** with its own authorization and
  audit rules. It is **not** implemented now, and nothing in the current design should be read as
  a backdoor for it.

### How this maps to the current (Phase 1G) implementation

The Workspace Super Admin identity is realized today by the tenant-resident `workspace-admin`
system role: it is created and granted to the initial admin during provisioning
(`CreateInitialWorkspaceAdminAction`), and it receives unrestricted authority over Workspace
abilities via the `Gate::before` short-circuit (see below). No separate "super admin" table or
flag exists — the system role *is* the mechanism.

> **Naming note (deliberately deferred):** the product term is "Workspace Super Admin"; the code
> currently names the role `workspace-admin`. This is intentional for now — the implementation is
> **not** being renamed in this phase. The naming decision will be made deliberately in a future
> phase. Treat "Workspace Super Admin" (product) and `workspace-admin` (current slug) as the same
> thing until then.

### Not now (future phases)

Do not add additional Workspace administrative roles, Workspace user onboarding flows, or
Platform→Workspace support-access features yet unless a current phase requires them. The initial
Workspace Super Admin is the only Workspace administrative identity at this stage.

## Where the data lives

Workspace RBAC is **tenant-resident**: the tables live in every Company's own workspace
database, created by tenant migrations (`database/migrations/tenant/2026_03_21_000001..000004`):

| Table                       | Purpose                                                        |
| --------------------------- | -------------------------------------------------------------- |
| `workspace_roles`           | Roles. ULID pk, `slug` unique, `is_system`, `status`, soft deletes. |
| `workspace_permissions`     | Permissions. ULID pk, `slug` unique, `status`, soft deletes.   |
| `workspace_user_roles`      | User↔role pivot (composite-unique, `noActionOnDelete` FKs).    |
| `workspace_role_permissions`| Role↔permission pivot (composite-unique, `noActionOnDelete` FKs).|

The models (`WorkspaceRole`, `WorkspacePermission`) and `WorkspaceUser` set **no explicit
connection** — they resolve on the dynamic `tenant` connection and are therefore only usable
inside tenant context (behind `tenant.resolve`). There is intentionally **no cross-DB
fallback**: outside tenant context a workspace permission lookup errors rather than silently
reading another database (proven in `WorkspacePlatformAuthorizationSeparationTest`).

## The model API

`WorkspaceUser` exposes the authorization surface:

- `roles()` — the user's roles (belongs-to-many).
- `isWorkspaceAdmin()` — true when the user holds the active `workspace-admin` system role.
  This is the Workspace analogue of `PlatformUser::isPlatformAdmin()`.
- `hasRole(string $slug)` — true for an active role by slug.
- `hasPermission(string $slug)` — true when any active role grants the active permission.
  Resolved entirely within the tenant database.

`WorkspaceRole::isAdminSystemRole()` identifies the protected admin role
(`is_system === true && slug === 'workspace-admin'`). On both models `is_system` and `status`
are **not** mass-assignable — only trusted system code sets them.

## How authorization is enforced (Gates, not Policies)

`App\Providers\WorkspaceAuthServiceProvider` (registered in `bootstrap/providers.php`) wires
two things, and deliberately uses Laravel **Gates** — not Policies, and no RBAC package.

### 1. The workspace-admin short-circuit (`Gate::before`)

```php
Gate::before(function (?Authenticatable $user, string $ability, array $arguments = []): ?bool {
    if ($arguments !== []) {
        return null; // Platform (CompanyPolicy) abilities target a model — never short-circuit them.
    }

    if ($user instanceof WorkspaceUser && $user->isWorkspaceAdmin()) {
        return true; // system-role authority over every WORKSPACE ability
    }

    return null; // everyone/everything else proceeds to normal gates/policies
});
```

This grants an active workspace admin **every workspace ability** — system-role authority, not
a wildcard permission record. It is scoped strictly in two independent ways:

1. **Only a `WorkspaceUser`** can ever trigger it.
2. **Only model-less abilities** trigger it (`$arguments === []`). Platform `CompanyPolicy`
   abilities (`viewAny`, `view`, `provision`, …) always target a `Company` model/class, so the
   short-circuit never fires for them. Returning `null` (never `false`) leaves all other
   authorization untouched, so Platform authorization and non-admin workspace checks are
   unaffected.

### 2. Permission-slug gates

```php
foreach (['workspace.access', 'workspace.manage'] as $slug) {
    Gate::define($slug, fn (?Authenticatable $user) =>
        $user instanceof WorkspaceUser && $user->hasPermission($slug));
}
```

A non-admin passes only if an active role grants the permission. A non-`WorkspaceUser` (e.g. a
`PlatformUser`) never passes a workspace gate.

### Route integration

The workspace home demonstrates the pattern:

```php
Route::get('/', fn () => Inertia::render('Workspace/Home'))
    ->middleware('can:workspace.access')
    ->name('workspace.home');
```

Server-side `can:` authorization is authoritative — it is independent of the
forced-password-change middleware and of any frontend check.

## Seeding

`App\Actions\Workspace\SeedWorkspaceRbacAction` seeds the baseline, **inside tenant context**:

- the protected `workspace-admin` system role (`is_system=true`, `status=1`),
- the Phase 1G **proof** permissions `workspace.access` and `workspace.manage`,
- assigns both permissions to the admin role **explicitly** (never a wildcard).

It is **idempotent and soft-delete-safe** (matches by slug `withTrashed`, restores, re-enforces
invariants — mirroring `PlatformRoleSeeder`), so it is safe to run repeatedly.

Two entry points, same Action:

- **Provisioning** — `CreateInitialWorkspaceAdminAction` runs the seed, then attaches
  `workspace-admin` to the initial admin. A freshly provisioned admin therefore already carries
  `workspace.access`.
- **Existing workspaces** — `php artisan workspace:seed-rbac {company|--all}` (skips companies
  with no workspace DB). `workspace:migrate` stays **structural only**; `workspace:seed-rbac` is
  the RBAC data path.

## Permission naming convention: `{module}.{action}`

Workspace permission slugs are `{module}.{action}`, lowercase, dot-separated. The baseline uses
the pseudo-module `workspace` (`workspace.access`, `workspace.manage`). Business modules use
their own module name:

```
invoice.view      invoice.create      invoice.post
customer.view     customer.manage
inventory.adjust
```

Keep actions small and verb-like (`view`, `create`, `update`, `delete`, `manage`, plus
domain-specific verbs such as `post` or `approve`).

## Adding permissions from a future business module

A module **registers and enforces** its own workspace permissions following this pattern — it
does not need to touch `WorkspaceAuthServiceProvider`:

1. **Seed** the module's permissions into the tenant DB and assign them to `workspace-admin`
   explicitly (reuse the idempotent, soft-delete-safe pattern from `SeedWorkspaceRbacAction`;
   run inside tenant context). The admin short-circuit means you do **not** also have to grant
   module permissions to the admin for the admin to pass — but assigning them keeps the data
   honest and makes non-short-circuit checks (e.g. listing a role's permissions) correct.
2. **Enforce** with either:
   - `can:{module}.{action}` middleware / `$this->authorize('{module}.{action}')` in a thin
     controller — resolves through `WorkspaceUser::hasPermission` with no extra wiring, or
   - a module-defined `Gate::define('{module}.{action}', …)` if the check needs arguments or
     custom logic beyond a plain permission lookup.
3. The workspace-admin short-circuit automatically covers the module's **model-less** abilities.
   If a module needs an admin to bypass a **model-targeted** ability too, define that policy to
   check `isWorkspaceAdmin()` itself — the global short-circuit deliberately does not fire for
   model-targeted abilities (that is what keeps it from leaking into Platform authorization).

## What Phase 1G intentionally did NOT build

- No RBAC **management UI** (no screen to create roles or assign permissions yet).
- No workspace user management beyond the initial admin.
- No authorization package (no Spatie Permission) and no Policies for workspace resources yet.
- Only the two proof permissions — real permissions arrive with the first business module.
