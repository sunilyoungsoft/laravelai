---
inclusion: always
---

# Project Status

Living status tracker. Update it when a phase completes or the architecture changes.
Verified against the repository on 2026-09-30 (Kiro takeover).

## Identity

- This is a modular, multi-tenant SaaS ERP/CRM platform.
- **The product name is not decided yet.** Do not hardcode a product name anywhere in code,
  config, UI strings, or docs. Use neutral wording (e.g. "the platform") until a name is chosen.
- The repository keeps its current name `laravelai` (README title "Laravel AI ERP",
  composer `laravel/laravel`) as scaffolding identity only — that is not the product name.

## Stack (verified)

- Laravel 13.32, PHP 8.4 (composer requires `>= 8.4.1`).
- MySQL: `platform` connection + `workspace` template connection.
- Redis (predis) for cache/queue; Laravel Queue + Scheduler.
- Frontend: React 19 + TypeScript + Inertia + Tailwind CSS 4 (+ shadcn/ui, initialized in Phase 1E).
- Tenancy infrastructure: `stancl/tenancy` v3.10.1 (already installed).
- Tests: PHPUnit; Pint for formatting.

## Completed (verified in repo)

- **Phase 0 / 1A** — foundation, health endpoints, Inertia React welcome.
- **Phase 1B — Platform RBAC** — `platform_users/roles/permissions` + pivots, models on
  `platform` connection, Admin system role seeder (explicit permissions, no wildcards),
  `php artisan platform:create-admin` (first admin bootstrap, soft-delete aware).
- **Phase 1C — Companies** — `companies` table on `platform`, `Company` model +
  `CompanyStatus` enum, `CompanyService`, app-generated `database_name` = `workspace_{ulid}`,
  profile-only `$fillable` (system fields guarded).
- **Application Layer proof** — `Modules/Demo` proves Web/Inertia and REST API converging on
  one DTO + one Action. Proof only; `DemoNote` is not CRM data. See
  `docs/architecture/application-layer.md`.
- **Tenancy Architecture Spike — COMPLETE.** Stancl integrated as infrastructure: `Company`
  implements `TenantWithDatabase` via `App\Tenancy\Concerns\CompanyIsStanclTenant`,
  `config/tenancy.php` + `App\Providers\TenancyServiceProvider`, `tests/Feature/Tenancy/`.
  Findings + acceptance checklist (all YES) in `docs/architecture/tenancy-spike.md`.
- **Phase 1D — Workspace Provisioning + Domains — COMPLETE.** Spec in
  `.kiro/specs/phase-1d-workspace-provisioning-domains/` (OD-1…OD-5 confirmed). Delivered:
  - Workspace migrations dir `database/migrations/tenant/` (+ `workspace_meta` sample).
  - `WorkspaceDatabaseService` (create/exists/guarded-drop/migrate/pending-count via the
    configured Stancl manager, DDL on the explicit `platform` connection).
  - `ProvisionWorkspaceAction` (pending/failed→provisioning via conditional-UPDATE claim;
    `active` rejected; suspended/deactivated/provisioning refused; delete+recreate on retry;
    verify; failure→`provisioning_failed`, never `active`).
  - Entry points: `workspace:provision` command + `ProvisionWorkspaceJob` (both call the same
    Action; job carries the ULID, resolves on `platform`).
  - Platform `domains` table + `Domain` model + `DomainType`/`DomainStatus` enums; `Hostname`
    value object; `ReservedLabels` (extracted from `CompanyService`, shared);
    `CreateDomainAction` / `SetPrimaryDomainAction` / `ActivateSubdomainAction`; configurable
    `config('tenancy.base_domain')` (env `PLATFORM_BASE_DOMAIN`, unset by default).
  - `ResolveCompanyByHostnameAction` (resolve-only, `platform`) + middleware
    `InitializeTenancyByResolvedDomain` (alias `tenant.resolve`) initializing Stancl tenancy.
  - `tests/Security/` isolation suite added (new PHPUnit `Security` testsuite).
- **Phase 1E — Platform Admin UI — COMPLETE.** Spec in
  `.kiro/specs/phase-1e-platform-admin-ui/` (decisions 1E-A…1E-E confirmed). Delivered:
  - **Frontend tooling (1E-D):** ESLint + Prettier (flat config) with `lint` / `lint:fix` /
    `format` / `type-check` npm scripts. shadcn/ui initialized for Tailwind v4 CSS-first
    (`components.json`, tokens in `resources/css/app.css` incl. added `success`/`warning`
    tokens, primitives under `resources/js/components/ui/`: button, badge, card, table,
    dialog, input, label).
  - **Platform auth (1E-A):** `LoginRequest` (generic non-disclosing credential error +
    rate limiting), `PlatformLoginController` (showLogin/login/logout via
    `Auth::guard('platform')`, session regenerate/invalidate), `routes/platform.php`
    (`guest:platform` login, `auth:platform` logout + admin area), `bootstrap/app.php`
    registration + `redirectGuestsTo(platform.login)` / `redirectUsersTo(platform.dashboard)`.
    No registration / password-reset / 2FA surfaces (decision A5).
  - **Authorization (1E-B):** `PlatformUser::isPlatformAdmin()` (active Admin system role:
    slug=admin, is_system=true, status=1), `App\Policies\CompanyPolicy`
    (viewAny/view/create/provision/manageDomains, all gated on `isPlatformAdmin`, registered
    via `Gate::policy` in `AppServiceProvider`), `HandleInertiaRequests` shares `auth.user` +
    `auth.can` (companies.viewAny/create) + `flash.success`/`flash.error`. Base `Controller`
    now uses `AuthorizesRequests`.
  - **Thin controllers + routes (Stage 3):** `DashboardController` (status counts,
    soft-deleted excluded), `CompanyController` (index with search + status filter +
    pagination + `withQueryString`; create/store via `CompanyService` = pending, NO
    provisioning; show with effective primary domain + per-company `can`),
    `WorkspaceProvisionController` (**SYNC** `ProvisionWorkspaceAction::execute`, catches
    `CompanyNotProvisionableException` + `WorkspaceProvisioningException`, success/error
    flash — decision 1E-E: provisioning from the admin UI is **synchronous in-request**; the
    queued job path remains unproven), `CompanyDomainController` store/update via
    `CreateDomainAction` + `SetPrimaryDomainAction` (+ `DomainRequest`; malformed custom
    hostname surfaces as a domain field validation error, not a 500 — decision 1E-C: the
    admin UI manages a **single effective (primary) domain** per company). All routes in
    `routes/platform.php` under `auth:platform`; `{company}` bound on the `platform`
    connection; controllers authorize via `CompanyPolicy` (no route `can:` middleware).
  - **Reusable components (Stage 4) + 5 Inertia pages (Stage 5):** PlatformLayout, PageHeader,
    CompanyStatusBadge, CompanyTable, CompanyInformationCard, WorkspaceStatusCard, DomainCard,
    EmptyState, ConfirmDialog, LoadingButton; pages `Platform/Auth/Login` (standalone),
    `Platform/Dashboard`, `Platform/Companies/{Index,Create,Show}`. No hardcoded product name
    (reads `appName`).
  - **Tests:** `PlatformAuthTest`, `CompanyAuthorizationTest`, `PlatformAdminControllersTest`
    (`tests/Feature/Platform`) and `PlatformAdminAccessControlTest` (`tests/Security` —
    non-admin 403 / guest redirect matrix for every action).
- **Phase 1F — Workspace Users + Workspace Login — COMPLETE.** The minimal end-to-end workspace
  authentication slice (decisions 1F-A…1F-G confirmed). Delivered:
  - **Hashing (1F-A):** `config/hashing.php` with `driver = argon2id` applied app-wide (Platform
    + Workspace) via Laravel's Hash abstraction — no custom hashing. Argon2id confirmed available
    on PHP 8.4. Pre-existing bcrypt hashes still verify and rehash to Argon2id on next login
    (`rehash_on_login = true`). NOTE: the Argon `verify` option is `false` by default
    (`HASH_VERIFY`) so the Argon2id hasher does not throw on legacy bcrypt hashes.
  - **Workspace users in the tenant DB (1F-B):** `database/migrations/tenant/..._create_workspace_users_table`
    (ULID, name, email unique-within-tenant, password, `must_change_password`, status,
    last_login_at, soft deletes). `App\Models\WorkspaceUser` resolves on the dynamic `tenant`
    connection — never the Platform DB. `docs/database/workspace.dbml` documents the schema.
  - **Workspace guard (1F-C):** `config/auth.php` guard `workspace` → provider `workspace_users`
    → `WorkspaceUser`. `defaults.guard` stays `platform`; workspace code names
    `Auth::guard('workspace')` explicitly. The guard is usable only behind `tenant.resolve`.
  - **Host-scoped routing (1F-D):** `bootstrap/app.php` registers `routes/platform.php` once per
    central domain (`Route::domain()` over `tenancy.central_domains`) so the admin UI (and its
    `/login`, plus the public `/` welcome, moved into `routes/platform.php`) answers only on
    central hosts. `routes/workspace.php` is served on any resolved Company host behind
    `tenant.resolve`. Guest/authenticated redirects are host-aware (workspace login/home on a
    Company host, platform login/dashboard on a central host).
  - **Initial Workspace Admin + one-time temp password (1F-E):** `CreateInitialWorkspaceAdminAction`
    (+ DTO, run inside tenant context) generates a secure `Str::password()` temporary password,
    stores only its Argon2id hash, sets `must_change_password`, and returns the plaintext once —
    never persisted, never logged. The provision flow (`ProvisionWorkspaceRequest` captures
    `workspace_admin_name`/`email`) calls it after a successful provision and flashes the temp
    password once on the Company Show page (one-shot session flash).
  - **Forced first-login change (1F-F):** `EnsureWorkspacePasswordChanged` middleware blocks every
    workspace route except the change-password + logout routes while `must_change_password` is
    set; the change controller verifies the current password, stores the new Argon2id hash, and
    clears the flag.
  - **Migrate existing workspaces (1F-G):** `MigrateWorkspaceAction` + `workspace:migrate`
    command run pending tenant migrations against one (`{company}`) or all active (`--all`)
    workspaces with no drop/recreate; idempotent. This is how already-active companies receive
    `workspace_users` without data loss.
  - **Frontend:** standalone `WorkspaceLayout` + Inertia pages `Workspace/Auth/{Login,ChangePassword}`
    and `Workspace/Home`; `HandleInertiaRequests` shares a separate `workspaceAuth` prop.
  - **Tests:** `tests/Feature/Workspace/*` (model/migration/guard/initial-admin/auth/migrate) and
    `tests/Security/WorkspaceAuthIsolationTest` (cross-tenant resolution isolation, host-only
    selection, central-host/unknown-host fail-safe, forced-change not bypassable, temp pw never
    logged). Workspace RBAC (roles/permissions) is explicitly a follow-up phase.

## Verification run (Phase 1F)

- `php artisan test` → **245 passed, 737 assertions** (PHP 8.4.25) — up from the 206 at the start
  of Phase 1F (which was itself up from 200 at Phase 1E, +6 hashing tests).
- `vendor/bin/pint --test` → **passed** (141 files).
- `npm run lint` / `npm run type-check` / `npm run build` → all **clean**.

## Not built yet

Workspace **RBAC** (roles/permissions per tenant — Phase 1F deliberately shipped auth only),
Workspace user management UI beyond the initial admin, subscriptions, module-system runtime
(install/enable/disable/update/uninstall), 2FA flows, password reset / email flows. No
business/ERP modules exist. AI/MCP not started. Custom-domain verification (DNS/SSL) is
intentionally out of scope so far (future work). Both the platform admin **login UI** (Phase 1E)
and the workspace **login UI** (Phase 1F) now exist; registration / password-reset / 2FA surfaces
are deliberately absent.

## Actual next step

Phase 1F (Workspace Users + Workspace Login) is done — workspace users can sign in on their
Company host, are forced to change the initial temporary password, and tenant isolation of
workspace auth is tested. Candidate next work: **Workspace RBAC** (roles/permissions per tenant),
workspace user management beyond the first admin, and/or custom-domain verification transport
(DNS/SSL). Notes carried forward: admin-UI provisioning is **synchronous in-request** (decision
1E-E) and queued-provisioning robustness remains **unproven**; `workspace:migrate` is the
supported path to apply new tenant migrations to existing active workspaces. Start any
significant next feature with a Kiro Spec.

## Environment notes

- The default `php` on PATH is 8.3.18 (too old — Composer platform check fails). Use a PHP 8.4
  binary to run artisan/pint, e.g. `C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64\php.exe`.
- Tests use `laravelai_platform_testing` + a testing DB guard; workspace spike DBs are created
  only under the test-only `spike_allow_test_databases` flag.
- **TypeScript is intentionally pinned to `^6.x` (6.0.3), NOT `^7.0.2`** — TS 7.0 has no stable
  programmatic API and `typescript-eslint@8.x` rejects it. Do not "silently fix"/bump this to 7.x;
  revisit when TS 7.1 + matching `typescript-eslint` support lands (Phase 1E decision 1E-D).
- **Password hashing is Argon2id app-wide** (`config/hashing.php`, Phase 1F decision 1F-A). The
  Argon `verify` option stays `false` (via `HASH_VERIFY`) so legacy bcrypt hashes still verify and
  rehash to Argon2id on next login — do not flip it to `true` while any bcrypt hash may remain.
- `workspace:migrate {company|--all}` runs pending tenant migrations against existing active
  workspaces without drop/recreate; use it (not re-provisioning) to roll out new tenant migrations.

## Known stale / conflicting docs to reconcile (do not silently "fix")

- `project-context.md` still says "Phase 0 foundation only; do not implement auth/RBAC/tenant
  provisioning" — stale now that 1B/1C and the tenancy spike are done.
- `code-quality.md` says "prefer Services over Actions"; the established application layer is
  **Action-first** (see `development-rules.md`). `development-rules.md` is authoritative for the
  application layer.
- Stray files in repo root (`count()`, and a `database/database.sqlite`) look like accidental
  artifacts. Flagged, not touched.
