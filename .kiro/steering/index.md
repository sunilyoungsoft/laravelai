---
inclusion: always
---

# Project Index — Start Here

This is the orientation map for the project. It ties together the steering rules,
tells you where things live, and tracks what has been built. Keep it current: when a
phase completes, a module is added, or the structure changes, update this file.

## What this project is

A **modular monolith ERP SaaS platform** built on Laravel 13 / PHP 8.4.
The long-term goal is a multi-company SaaS where each company gets its own
workspace database, and business features are shipped as installable modules.

- Multi-company SaaS (one codebase, many tenants)
- Separate database per company (tenant), plus one shared Platform database
- Business features delivered as enable/disable/installable modules under `Modules/`

## Stack

| Layer      | Choice                                                |
| ---------- | ----------------------------------------------------- |
| Backend    | Laravel 13, PHP 8.4                                    |
| Database   | MySQL 8.x — `platform` connection + per-company `workspace` DBs |
| Cache/Queue| Redis (predis), Laravel Queue + Scheduler             |
| Frontend   | Inertia.js + React 19 + TypeScript + Tailwind CSS 4   |
| Auth/Hash  | `platform` + `workspace` session guards; Argon2id (Hash abstraction) |
| Testing    | PHPUnit 12                                             |
| Tooling    | Vite 7, Laravel Pint, Pail, ESLint + Prettier, shadcn/ui |

## Steering rules (auto-loaded context)

All rules live in `.kiro/steering/`. These are always in my context:

| File                  | Covers                                            |
| --------------------- | ------------------------------------------------- |
| `index.md`            | This file — project map and status                |
| `project-context.md`  | Project identity, non-negotiable architecture     |
| `architecture.md`     | Core/layers, thin controllers, services vs actions|
| `tenancy.md`          | Platform vs tenant DB, TenantContext, isolation   |
| `modules.md`          | Module layout, lifecycle, communication           |
| `database.md`         | Schema conventions (ULID, snake_case, FKs, UTC)   |
| `api.md`              | API standards                                     |
| `frontend.md`         | Inertia/React/TS/Tailwind conventions             |
| `events-queues.md`    | Events, listeners, jobs                           |
| `security.md`         | Security rules                                    |
| `code-quality.md`     | Code quality expectations                         |
| `project-style.md`    | Style conventions                                 |
| `testing.md`          | Testing standards + tenant isolation tests        |

## Where things live

```
app/                         Shared/core code only (never module business logic)
  Console/Commands/          Artisan commands (e.g. CreatePlatformAdminCommand)
  Enums/                     Shared enums (CompanyStatus)
  Http/Controllers/          Core controllers (Health, Welcome)
  Http/Middleware/           HandleInertiaRequests
  Models/                    Platform models (PlatformUser/Role/Permission, Company)
  Providers/                 AppServiceProvider
  Services/Platform/         Platform services (CreatePlatformAdminService)
Modules/{ModuleName}/        Business modules (none built yet — placeholder)
config/                      Laravel config (platform DB connection, etc.)
database/
  migrations/                Platform tables (users, roles, permissions, companies)
  factories/                 Model factories
  seeders/                   PlatformRoleSeeder, DatabaseSeeder
docs/database/platform.dbml  Platform schema — keep in sync with migrations
resources/js/                Inertia React + TypeScript frontend
routes/                      web.php, api.php, console.php
tests/Feature, tests/Unit    PHPUnit tests
```

## Build status

- **Phase 0 — Foundation:** done. Skeleton, Redis queue/cache, Inertia React welcome
  page, health endpoints (`/up`, `/api/health`), `Modules/` placeholder.
- **Phase 1B — Platform RBAC:** done. `platform` DB connection, `platform_users/roles/permissions`
  + pivots, models, `platform` auth guard, Admin role seeder, `php artisan platform:create-admin`.
- **Phase 1C — Companies:** done. `companies` table on `platform`, `Company` model +
  `CompanyStatus` enum, `CompanyService`, app-generated `database_name` (`workspace_{ulid}`).
- **Phase 1D — Workspace Provisioning + Domains:** done. `WorkspaceDatabaseService` +
  `ProvisionWorkspaceAction` (create/migrate/verify/activate, delete+recreate on retry,
  guarded drop), `workspace:provision` command + `ProvisionWorkspaceJob`, platform `domains`
  table + `Domain` model/enums + Domain Actions, hostname resolver + `tenant.resolve`
  middleware, `tests/Security/` suite. Full suite 136 passed; Pint clean. See
  `.kiro/specs/phase-1d-workspace-provisioning-domains/`.
- **Phase 1E — Platform Admin UI:** done. Platform login (`Auth::guard('platform')`,
  `routes/platform.php`, no registration/reset/2FA), `PlatformUser::isPlatformAdmin()` +
  `CompanyPolicy` (viewAny/view/create/provision/manageDomains), thin controllers
  (Dashboard, Company index/create/show, Workspace provision, Company domain) all authorized
  via policy. Admin-UI provisioning is **synchronous in-request** (1E-E); the UI manages a
  **single effective (primary) domain** per company (1E-C). Frontend tooling added (ESLint +
  Prettier, lint/format/type-check scripts; **TypeScript pinned to `^6.x`, not 7.x** — 1E-D),
  shadcn/ui initialized for Tailwind v4 (ui primitives + reusable Platform components), 5
  Inertia pages. `tests/Feature/Platform` + `tests/Security` access-control matrix. Full
  suite 200 passed; Pint clean; lint/type-check/build clean. See
  `.kiro/specs/phase-1e-platform-admin-ui/`.
- **Phase 1F — Workspace Users + Workspace Login:** done. App-wide **Argon2id** hashing
  (`config/hashing.php`, 1F-A; legacy bcrypt still verifies + rehashes on login). Tenant-resident
  `workspace_users` migration + `WorkspaceUser` model (1F-B, never in the Platform DB). A
  `workspace` auth guard (1F-C; `defaults.guard` stays `platform`). Platform routes host-scoped to
  central domains; workspace login/logout + forced-password-change served on resolved Company hosts
  behind `tenant.resolve` (1F-D), with host-aware guest/user redirects. Initial Workspace Admin
  created at provisioning with a one-time temporary password shown once (1F-E), forced change on
  first login via `EnsureWorkspacePasswordChanged` (1F-F). `workspace:migrate {company|--all}`
  applies pending tenant migrations to existing active workspaces without drop/recreate (1F-G).
  Standalone workspace Inertia pages + layout. `tests/Feature/Workspace` + a
  `tests/Security/WorkspaceAuthIsolationTest` tenant-isolation matrix. Full suite 245 passed; Pint
  clean; lint/type-check/build clean. Workspace **RBAC** is a follow-up. See
  `.kiro/specs/phase-1f-workspace-auth/` (plan tracked in session).

### Not built yet
Workspace **RBAC** (roles/permissions per tenant), workspace user management beyond the initial
admin, subscriptions, module system runtime, 2FA, password reset, custom-domain verification
(DNS/SSL), queued-provisioning robustness (still unproven — admin UI provisions synchronously).
No business modules exist yet. (Both platform admin login (Phase 1E) and workspace login (Phase
1F) now exist.)

## Key invariants (do not violate)

- **Tenant isolation:** never run tenant business queries without a valid TenantContext,
  and never silently fall back to the Platform DB. Company A must never see Company B data.
- **Platform vs tenant data:** platform-level data (companies, users, plans, subscriptions,
  modules, domains) stays in the Platform DB; business data lives in tenant DBs.
- **Admin role:** the Admin system role uses explicit permissions, never wildcards. New
  platform permissions must be assigned to Admin.
- **Soft-delete identity:** `email`, `phone`, role/permission `slug`, company `slug` and
  `database_name` stay reserved after soft delete — restore, don't duplicate.
- **Schema conventions:** ULID primary keys, snake_case plural tables, FKs use `NO ACTION`
  (`noActionOnDelete()`), timestamps in UTC (no MySQL `NOW()`/`CURRENT_TIMESTAMP`).
- **Modules explicit connection:** platform models/migrations/seeders/services set the
  `platform` connection explicitly; platform auth uses `Auth::guard('platform')`. Workspace
  models (e.g. `WorkspaceUser`) set NO connection — they resolve on the dynamic `tenant`
  connection and are only usable inside tenant context; workspace auth uses
  `Auth::guard('workspace')`.
- **Keep `docs/database/platform.dbml` and `docs/database/workspace.dbml` updated** whenever the
  platform or workspace (tenant) schema changes.

## Common commands

```bash
composer run dev        # server + queue + logs + vite together
php artisan test        # run tests
npm run build           # build frontend
php artisan platform:create-admin   # bootstrap first Platform Admin
```
