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
- Frontend: React 19 + TypeScript + Inertia + Tailwind CSS 4 (+ shadcn/ui per brief).
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

## Verification run (Phase 1D)

- `php artisan test` → **136 passed, 344 assertions** (PHP 8.4.25) — up from the 74 baseline.
- `vendor/bin/pint --test` → **passed** (101 files).

## Not built yet

Workspace users + Workspace RBAC, subscriptions, module-system runtime
(install/enable/disable/update/uninstall), login UI, 2FA flows, password reset. No business/ERP
modules exist. AI/MCP not started. Custom-domain verification (DNS/SSL) is intentionally out of
Phase 1D scope (future work).

## Actual next step

Phase 1D (provisioning + Domains) is done. Candidate next work: custom-domain verification
transport (DNS/SSL) and/or Workspace users + Workspace RBAC. Note: Redis/Horizon queue-worker
robustness and failed-job retry paths for provisioning are still **unproven** (only the sync
driver is exercised) — verify before relying on queued provisioning in production. Start any
significant next feature with a Kiro Spec.

## Environment notes

- The default `php` on PATH is 8.3.18 (too old — Composer platform check fails). Use a PHP 8.4
  binary to run artisan/pint, e.g. `C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64\php.exe`.
- Tests use `laravelai_platform_testing` + a testing DB guard; workspace spike DBs are created
  only under the test-only `spike_allow_test_databases` flag.

## Known stale / conflicting docs to reconcile (do not silently "fix")

- `project-context.md` still says "Phase 0 foundation only; do not implement auth/RBAC/tenant
  provisioning" — stale now that 1B/1C and the tenancy spike are done.
- `code-quality.md` says "prefer Services over Actions"; the established application layer is
  **Action-first** (see `development-rules.md`). `development-rules.md` is authoritative for the
  application layer.
- Stray files in repo root (`count()`, and a `database/database.sqlite`) look like accidental
  artifacts. Flagged, not touched.
