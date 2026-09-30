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

## Verification run (2026-09-30)

- `php artisan test` → **74 passed, 234 assertions** (run with PHP 8.4.25).
- `vendor/bin/pint --test` → **passed** (72 files).
- Note: the tenancy-spike doc still cites 64/193 (pre-existing; the suite has grown since).

## Not built yet

Domains, Workspace DB provisioning (create/migrate/drop), Workspace users + Workspace RBAC,
subscriptions, module-system runtime (install/enable/disable/update/uninstall), login UI,
2FA flows, password reset. No business/ERP modules exist. AI/MCP not started.

## Actual next step

**Provisioning + Domains design (Phase 1D+), NOT the tenancy spike** — the spike is already
done. Next work should design workspace provisioning and the custom Domains table separately,
without rewriting Platform RBAC or Companies, and without enabling Stancl `CreateDatabase` by
default. Start with a Kiro Spec (requirements → design → tasks) before implementing.

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
