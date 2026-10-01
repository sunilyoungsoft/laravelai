# Phase 1D — Workspace Provisioning + Domains — Design

## Overview

This design implements two application-owned capabilities on top of the completed Stancl
tenancy spike:

1. **Workspace provisioning** — an Action-first workflow that creates the physical Workspace
   database for a `Company`, migrates it, verifies it, and drives the `CompanyStatus`
   lifecycle to `active` (or `provisioning_failed`).
2. **Platform Domains** — an application-owned `domains` table + model + Actions on the
   `platform` connection, plus a resolver that maps an incoming hostname to a `Company` and
   initializes tenancy through Stancl.

The design deliberately reuses established pieces (`Company`, `CompanyStatus`,
`CompanyIsStanclTenant`, `config/tenancy.php`, the guarded test conventions) and Stancl's
existing `MySQLDatabaseManager` primitives. It adds **no** custom `TenantManager`, no generic
Managers/Helpers/Repositories, and no `BaseAction`.

> Open decisions **OD-1…OD-5 are CONFIRMED** (see `tasks.md` section 0 and `requirements.md`).
> This design has been reconciled with the confirmed answers; earlier "recommended" wording has
> been replaced by the confirmed policy. `[OD-n]` tags below simply cite which decision governs
> a section; they no longer mean "pending".

---

## Guiding constraints (from established architecture)

- `Company` is the tenant entity; `companies.database_name` = `workspace_{lowercase_ulid}` is
  authoritative and app-owned. Do not re-derive or rename it.
- Enter/leave tenant context only via Stancl (`tenancy()->initialize($company)` /
  `tenancy()->end()`, or `$company->run(fn () => ...)`). No manual DB switching.
- Platform models/queries name the `platform` connection explicitly.
- Do **not** enable Stancl's `CreateDatabase`/`MigrateDatabase`/`DeleteDatabase` *event
  pipelines*. The provisioning Action calls the database-manager primitives directly and runs
  Workspace migrations explicitly, so provisioning stays an intentional operation rather than
  an implicit side effect of Company creation.
- Transactions are explicit and only where atomicity is required. Physical `CREATE DATABASE`
  and migrations are **not** transactional in MySQL and must not be wrapped as if they were.

---

## Part A — Workspace Provisioning

### A.1 Components

| Component | Type | Location (proposed) | Responsibility |
|-----------|------|---------------------|----------------|
| `ProvisionWorkspaceAction` | Action | `app/Actions/Platform/ProvisionWorkspaceAction.php` | Orchestrates the provisioning workflow for one Company. Thin, reads as a story. |
| `WorkspaceDatabaseService` | Service | `app/Services/Platform/WorkspaceDatabaseService.php` | Genuine shared responsibility: create/exists/drop physical DB + run Workspace migrations, via Stancl's configured database manager. Wraps infrastructure, reused by the Action and by retry/console paths. |
| `WorkspaceProvisioningState` (or methods on `Company`) | status transitions | `app/Services/Platform/` or Company methods | Centralized, guarded status transitions so `active` is only ever set on success. |
| Workspace migrations dir | migrations | `database/migrations/tenant/` | The Workspace schema. Config already points `--path` here; the directory must be created. |
| `ProvisionWorkspaceCommand` | Console entry | `app/Console/Commands/ProvisionWorkspaceCommand.php` | Thin CLI entry that resolves a Company and calls the Action. (OD-4 confirmed) |
| `ProvisionWorkspaceJob` | Queued entry | `app/Jobs/Platform/ProvisionWorkspaceJob.php` | Queued entry carrying the Company ULID, resolving on `platform`, calling the same Action. (OD-4 confirmed) |

`WorkspaceDatabaseService` is justified as a Service (not folded into the Action) because
create/exists/migrate/drop of a Workspace DB is a cohesive infrastructure responsibility
reused by the Action, retry, verification, and (per OD-1) failure cleanup — this matches the
"genuine shared domain/infra responsibility" bar in the code-quality rules.

### A.2 Provisioning workflow (orchestrator)

`ProvisionWorkspaceAction::execute(Company $company, ?PlatformUser $actor)` reads as a story
(no `force` parameter — OD-2 confirms there is no force/repair flow):

```
execute()
  → assertProvisionable()          // status guard (A3): pending/provisioning_failed proceed;
  //                                  active → reject; provisioning → refuse concurrent;
  //                                  suspended/deactivated → refuse
  → markProvisioning()             // pending|provisioning_failed → provisioning (conditional UPDATE)
  → freshDatabase()                // OD-1: drop existing (guarded) if present, then create fresh
  → runWorkspaceMigrations()       // migrate the tenant DB only, inside Stancl tenant context
  → verifyWorkspace()              // A5 checks inside tenant context
  → markActive()                   // provisioning → active (platform tx)
```

On any thrown failure (or timeout), a single boundary `catch` records the failing step and
transitions the Company to `provisioning_failed`, then rethrows. The Company is never left in
`provisioning` after the run, and never becomes `active` on a failed/partial run. Cleanup of a
partial database is **not** performed in the failure path; instead the next retry starts with
`freshDatabase()`, which drops the stale DB and recreates it (OD-1 confirmed: delete + recreate
on retry, never reuse a partial database).

Status writes happen on the `platform` connection and are small explicit transactions. The
physical-DB and migration steps are **outside** any DB transaction (DDL is non-transactional).

### A.3 How database work uses Stancl (no custom switching)

`WorkspaceDatabaseService` uses the **configured** Stancl/database infrastructure rather than
coupling to a concrete manager class. It obtains the tenant database manager that Stancl has
configured for the Company via the supported accessor `$company->database()->manager()` (the
`HasDatabase` trait's `DatabaseConfig` resolves the manager from `config/tenancy.php`
`managers`). Whatever concrete manager is configured (MySQL today) satisfies the
`TenantDatabaseManager` contract, which exposes:

- `createDatabase(TenantWithDatabase $tenant): bool`
- `databaseExists(string $name): bool`
- `deleteDatabase(TenantWithDatabase $tenant): bool`
- `setConnection(string $connection): void`
- `makeConnectionConfig(...)`

Because `Company` implements `TenantWithDatabase` (via `CompanyIsStanclTenant`), the service
passes the `Company` straight to these primitives — the DB name comes from
`getInternal('db_name')` → `database_name`, so there is exactly one naming source of truth. The
service does not hand-write `CREATE`/`DROP` SQL and does not reference `MySQLDatabaseManager`
directly (Task 2.1 correction: use the configured infrastructure/supported APIs, not an
internal implementation detail).

**Server-level DDL runs on the `platform` connection, named explicitly.** Before calling
`createDatabase`, `deleteDatabase`, or `databaseExists`, the service sets the manager's
connection to the central connection via `$manager->setConnection('platform')` (part of the
`TenantDatabaseManager` contract). `CREATE DATABASE` / `DROP DATABASE` and the schema-existence
check are server-level operations and must execute on `platform`, never on a tenant connection.
This matches how the guarded test helper already creates workspace DBs.

**Guarded deletion (OD-1).** `dropDatabase(Company)` asserts the target name equals
`$company->database_name` and matches `^workspace_[0-9a-z]{26}$` before delegating to the
manager's `deleteDatabase`. Any mismatch throws; the Platform DB and any non-`workspace_`
database can never be dropped.

**No manual tenant connection switching.** The service never calls `DB::setDefaultConnection`,
never mutates `config('database.default')`, and never builds an ad-hoc tenant connection.
Entering tenant context — for migrations and verification — happens only through Stancl's
supported APIs (`$company->run(...)`, or `tenancy()->initialize($company)` / `tenancy()->end()`),
which own switching the default connection to the dynamic `tenant` connection and reverting to
`platform` afterward.

Workspace migrations run **inside tenant context** so they target only the tenant DB:

```php
$company->run(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
        '--force' => true,
        // migrations run on the default connection, which is `tenant` inside run()
    ]);
});
```

`$company->run()` is Stancl's supported "initialize → callback → end" helper; it guarantees
the default connection is the dynamic `tenant` connection during migration and reverts to
`platform` afterward. Migration state is tracked by the standard Laravel `migrations` table
**inside each Workspace database** (each tenant DB gets its own migration ledger), satisfying
"independently executable per Workspace" and "never runs against Platform".

> Note: the empty Stancl event pipelines in `TenancyServiceProvider` stay empty. We are not
> re-enabling `CreateDatabase`/`MigrateDatabase` as `TenantCreated` listeners; provisioning is
> invoked explicitly by the Action.

### A.4 Status lifecycle and guards (A1–A4)

Valid Phase 1D provisioning entry statuses and outcomes:

| Current status | `provision()` behavior |
|----------------|------------------------|
| `pending` | Proceed: → `provisioning` → … → `active` / `provisioning_failed`. |
| `provisioning_failed` | Retry: → `provisioning` → … → `active` / `provisioning_failed`. |
| `provisioning` | Refuse (concurrency guard, A3.4/A6.1): fail safely, no state change. |
| `active` | **Reject with a clear error** (A3.3, OD-2): not a no-op success, no reprovision, no force/repair. |
| `suspended` | Refuse, fail safely (A3.5). |
| `deactivated` | Refuse, fail safely (A3.6). |

**Concurrency guard (A6.1).** The `pending|provisioning_failed → provisioning` transition is
performed as a **conditional update** on the `platform` connection:

```
UPDATE companies SET status='provisioning', updated_by=?, updated_at=?
WHERE id=? AND status IN ('pending','provisioning_failed')
```

If zero rows are affected, another run already claimed it (or the status is not provisionable)
→ refuse. This makes the claim atomic without a custom lock and is safe under concurrent
requests/workers.

### A.5 Verification (A5)

`verifyWorkspace()` runs inside `$company->run(...)` and asserts:

1. `databaseExists($company->database_name)` is true (via the manager on `platform`).
2. Inside tenant context, `config('database.default') === 'tenant'` and the active connection's
   database name equals `companies.database_name`.
3. There are **no pending Workspace migrations** (compare migration files under
   `database/migrations/tenant` to the tenant `migrations` table).
4. It never substitutes a Platform read for a Workspace read.

Any failure throws → treated as provisioning failure (A2.3).

### A.6 Idempotency, retry, existing-DB policy (A6, A7) — **[OD-1 confirmed]**

Confirmed policy: **delete + recreate on retry; never reuse a partial database.**

- A run may only begin by atomically claiming the Company into `provisioning` (the conditional
  UPDATE in A.4). A Company already `provisioning` cannot start a second run.
- `freshDatabase()` at the start of the workflow: if `databaseExists($company->database_name)`
  is true (e.g. a leftover from a prior failed attempt), the service **drops** it via the
  guarded `dropDatabase` and then **creates** a fresh database. It never reuses an existing
  Workspace database.
- On failure/timeout at any step, the Company goes to `provisioning_failed` and the (possibly
  partial) database is **left in place**. The failure path does not drop it; the next retry's
  `freshDatabase()` performs the delete + recreate. This keeps the destructive `DROP` on the
  deliberate retry path (guarded) rather than scattered in error handling.
- Retry is permitted **only** from `provisioning_failed`.
- Guarded deletion (A7): `dropDatabase` refuses any name that is not the Company's own
  `workspace_{ulid}` `database_name`, so cleanup can never mis-target.

### A.7 Transaction boundaries (provisioning)

- Status transitions: small explicit transactions on `platform`.
- `CREATE DATABASE` and `migrate`: **no** surrounding DB transaction (DDL is auto-committing in
  MySQL; wrapping is misleading and unsafe).
- No `BaseAction`; the Action wraps only what needs atomicity.

### A.8 Entry points: Command + Job (both call the same Action) — **[OD-4 confirmed]**

Two entry points, one Action. Neither contains provisioning logic:

- `ProvisionWorkspaceCommand` (Artisan, manual/admin) resolves the Company by id/slug on
  `platform` and calls `ProvisionWorkspaceAction`.
- `ProvisionWorkspaceJob` (queued, async) carries the **Company ULID** only, re-resolves the
  Company on `platform` at run time, and calls the **same** `ProvisionWorkspaceAction`.

Stancl's `QueueTenancyBootstrapper` propagates tenant context for jobs that run *inside* tenant
context, but provisioning runs mostly from the **central** context (status writes +
`CREATE DATABASE`) and only enters tenant context for migration/verification via
`$company->run()`. The job therefore does not assume it starts in tenant context and does not
hand-roll tenant switching. A failed job leaves the Company in `provisioning_failed`, never
`provisioning`. Redis/Horizon worker behavior and failed-job retry paths remain **unproven** by
the spike (§G/§J) and are a follow-up verification risk, not an assumption.

---

## Part B — Domains

### B.1 Data model

New Platform table `domains` (connection `platform`), following existing conventions (ULID PK,
snake_case, soft deletes, audit FKs `NO ACTION`, UTC timestamps via Laravel):

| Column | Type | Notes |
|--------|------|-------|
| `id` | ulid, pk | |
| `company_id` | ulid, not null, indexed, FK → `companies.id` `NO ACTION` | owning Company |
| `domain` | varchar(253), not null | normalized hostname; global unique (see B.3) |
| `type` | varchar | `DomainType` enum: `subdomain` \| `custom` |
| `is_primary` | boolean, default false | one true per Company (see B.4) |
| `status` | varchar, indexed | `DomainStatus` enum: `pending` \| `active` \| `inactive` \| `verification_failed` |
| `verified_at` | timestamp, nullable | separate from status; not a status value |
| `created_by` | ulid, not null, FK → `platform_users.id` `NO ACTION` | |
| `updated_by` | ulid, nullable, FK → `platform_users.id` `NO ACTION` | |
| `created_at` / `updated_at` | timestamps | |
| `deleted_at` | timestamp, nullable | soft deletes |

Indexes: unique on `domain` (soft-delete-reserving, see B.3), index on `company_id`, index on
`status`. A partial/enforced "one primary per company" constraint is discussed in B.4.

**Enums** (reuse the `CompanyStatus` string-enum style):
- `app/Enums/DomainType.php` — `Subdomain = 'subdomain'`, `Custom = 'custom'`.
- `app/Enums/DomainStatus.php` — `Pending`, `Active`, `Inactive`, `VerificationFailed`.

**Model** `app/Models/Domain.php`: `protected $connection = 'platform'`, `HasUlids`,
`SoftDeletes`, casts for `type`/`status`/`verified_at`/`is_primary`, profile-only `$fillable`
with system fields (`company_id`, `is_primary`, `status`, `verified_at`, audit) guarded and set
by trusted code — mirroring the `Company` model's guarding style. Relations: `company()`,
`creator()`, `updater()`.

> `config('tenancy.domain_model')` currently points at Stancl's stock `Domain`. Since we do
> **not** use Stancl domain identification, this app `Domain` model is independent of that
> config. Design does not repoint or rely on `tenancy.domain_model`; the stock domains table is
> never migrated. (Whether to null out that config key to avoid confusion is a minor cleanup,
> noted in tasks, not required.)

### B.2 Normalization & validation (B2)

A single normalization routine (owned by the Domain Action/DTO boundary, not a generic helper)
performs, in order: `trim` → lowercase → strip one trailing `.`. Validation then:

- Rejects a value containing `://` (protocol), `/` (path), `:` followed by digits (port), or a
  leading `*.` (wildcard).
- Validates hostname syntax (labels of `[a-z0-9-]`, not starting/ending with `-`).
- Enforces total length ≤ 253 and each label 1–63 chars.

Normalization lives with the Domain application boundary (Form Request → DTO → Action). It is
not a `Helper` class; it is a small private method / value normalization on the DTO, consistent
with `CompanyService`'s inline normalization.

### B.3 Uniqueness & soft-delete identity reservation (B3)

- DB-level unique index on `domain` that **includes soft-deleted rows** (a plain unique index,
  not filtered by `deleted_at`), matching the platform identity-reservation policy already used
  for `companies.slug`/`database_name`.
- Application validation uses a connection-qualified `unique:platform.domains,domain` rule
  **without** `withoutTrashed()` (exactly the pattern `CompanyService` uses for slug), so
  soft-deleted values stay reserved.
- Reuse of a soft-deleted hostname is by **restoring** the existing record, never inserting a
  duplicate.

### B.4 Primary Domain invariant (B4)

Actions:
- `SetPrimaryDomainAction` — demote current primary, promote target, **atomically** in a single
  `platform` transaction:

```php
DB::connection('platform')->transaction(function () use ($company, $target) {
    Domain::on('platform')->where('company_id', $company->id)
        ->where('is_primary', true)->update(['is_primary' => false, ...]);
    $target->forceFill(['is_primary' => true, ...])->save();
});
```

Concurrency: the transaction plus a **row lock** (`lockForUpdate()` on the Company's domains)
prevents two concurrent promotions from both winning. Optionally reinforce with a unique index
on `(company_id, is_primary)` limited to primary rows — MySQL lacks partial indexes, so the
practical enforcement is the transaction + lock (recommended), with a documented invariant.
Enforcement is server-side only; UI is not trusted (B4.4).

### B.5 Domain Actions (Action-first)

| Action | Responsibility |
|--------|----------------|
| `CreateDomainAction` | Normalize+validate, enforce uniqueness, persist Domain for a Company with audit + initial status/type. |
| `SetPrimaryDomainAction` | Atomic primary switch (B.4). |
| `ActivateSubdomainAction` (or a status transition on the Domain Action) | Trusted activation of a `subdomain` (B5.1). Custom domains stay `pending`. |

DTOs (typed readonly) carry inputs; Form Requests validate at the HTTP boundary and build the
DTO; Actions perform the operation. No repository layer.

### B.6 Subdomain vs custom (B5) — **[OD-5 confirmed]**

- `subdomain` = a label under a **configurable base domain**. The base domain is read from
  config/env (e.g. a new `config('tenancy.base_domain')` backed by an env var); **no production
  domain is hardcoded**. If the base domain is not configured, subdomain construction/validation
  fails safely rather than guessing a host. Subdomain hostname = `"{label}.{base_domain}"`.
- Subdomain labels reject the existing reserved list (`api`, `admin`, `www`, `platform`, …).
  The reserved list already lives as `CompanyService::RESERVED_SLUGS`; to avoid duplicating the
  business rule (project-style §14), extract it to a shared source of truth (e.g. a small
  `ReservedLabels` value/enum or a config array) that both `CompanyService` and subdomain
  validation reference. *(This extraction is a DRY refactor of an existing rule, not a new
  abstraction; it does not change `CompanyService` behavior.)*
- `custom` = customer-owned hostname; created as `pending`; **no** auto-activation, **no** DNS
  or SSL work in Phase 1D (documented future work, B5.4).

---

## Part C — Tenant Resolution Boundary

### C.1 Resolver

`ResolveCompanyByHostnameAction` (or `TenantResolver` service if reused across entry points):

```
resolve(string $hostname): Company
  → normalize hostname (same routine as B.2)
  → Domain::on('platform')->where('domain', $normalized)->first()   // Platform data only
  → guard: domain found, in a resolvable state (e.g. active), company loaded
  → return the owning Company (do NOT initialize tenancy here)
```

The **HTTP middleware** (not the resolver) initializes tenancy: `tenancy()->initialize($company)`.
Splitting "resolve" (Action) from "initialize" (middleware) keeps resolution unit-testable and
keeps the Stancl call at the entry boundary. (OD-3 confirmed.)

Rules enforced:
- Lookup is on `platform` only; never a Workspace query (C1.5).
- Never Stancl's stock domains table (C1.6).
- Unknown/unresolvable hostname → throw a safe "not resolvable" result; no tenancy initialized,
  no Platform fallback for tenant data (C1.7).
- Returns exactly the Company owning the Domain; A-owned domain can't yield B (C1.8, D1.2).
- The resolver takes the hostname from the trusted request host only; it never reads a
  client-supplied `company_id`/`tenant_id`/database identifier (C1.9, D1.4).

### C.2 HTTP middleware in Phase 1D — **[OD-3 confirmed]**

Phase 1D implements both the resolver Action **and** the HTTP middleware. The middleware:

1. Reads the request host; if it is a central domain (`config('tenancy.central_domains')`), it
   passes through without initializing tenancy.
2. Otherwise calls `ResolveCompanyByHostnameAction` to get the Company (Platform data only).
3. Initializes tenancy via `tenancy()->initialize($company)`, then `$next($request)`.
4. On unknown/unresolvable host, aborts safely (e.g. 404) without initializing tenancy and
   without any Platform fallback for tenant data.

It is registered on the appropriate route group (workspace-facing routes). Client-supplied
identifiers can never select the Workspace — only the resolved host does.

---

## Part D — Security & Isolation (design realization)

- Isolation is inherited from the proven spike: Platform stays central; tenant queries run only
  inside `initialize()/run()`; default connection reverts to `platform` on end.
- No entry point accepts a client-chosen database/company/tenant id for Workspace selection;
  Workspace is always derived from resolved Platform data (Domain → Company) or an
  authenticated/authorized operator action (provisioning) (D1.4).
- Provisioning acts only on its target Company; status transitions are keyed by Company ULID
  (D1.6). Conditional-update guard prevents wrong-Company or double activation.
- Negative/abuse tests (Security group) assert cross-company denial and safe failure on missing
  context (D1.1–D1.3).
- Domain resolution cannot yield cross-company access because `domain` is globally unique and
  maps to exactly one `company_id` (D1.2).

---

## Part E — Testing strategy

Reuse existing conventions: `RefreshDatabase` on `platform`, the `GuardedWorkspaceDatabase`
helper (guards platform testing DB + `spike_allow_test_databases` + `workspace_{ulid}` name),
`QUEUE_CONNECTION=sync`, PHP 8.4. Add a `tests/Security/` group (or `tests/Feature/Security/`)
per the testing steering for negative isolation tests.

Test groups (map to E1):

- `tests/Feature/Provisioning/…` — happy path (create → migrate → verify → active), each
  failure path → `provisioning_failed`, retry, duplicate-execution guard (conditional update),
  invalid-status refusals, DB identity equals `database_name`, Platform DB untouched.
- `tests/Unit/Domains/…` + `tests/Feature/Domains/…` — normalization, invalid hostnames,
  uniqueness incl. soft-deleted reservation, multiple domains per company, single primary,
  atomic primary swap, subdomain vs custom, status transitions.
- `tests/Feature/Tenancy/Resolution/…` — hostname → correct Company, unknown → safe failure,
  A-domain cannot initialize B, resolved Company initializes via Stancl, Platform central
  before/after, Workspace isolation.
- `tests/Security/…` — cross-company denial, missing-context safe failure, no client DB
  selection.

Do not duplicate `TenancySpikeTest`; build Phase 1D tests around new behavior. Workspace
migrations used in tests live under `database/migrations/tenant`.

---

## Files to add/modify (summary)

**Add**
- `app/Actions/Platform/ProvisionWorkspaceAction.php`
- `app/Services/Platform/WorkspaceDatabaseService.php`
- `app/Console/Commands/ProvisionWorkspaceCommand.php` (OD-4)
- `app/Jobs/Platform/ProvisionWorkspaceJob.php` (OD-4)
- `database/migrations/tenant/` (dir) + at least one Workspace migration (may start minimal)
- `app/Enums/DomainType.php`, `app/Enums/DomainStatus.php`
- `app/Models/Domain.php`
- `database/migrations/2026_..._create_domains_table.php` (platform)
- `database/factories/DomainFactory.php`
- `app/Actions/Platform/Domains/CreateDomainAction.php`, `SetPrimaryDomainAction.php`,
  `ActivateSubdomainAction.php`
- DTOs + Form Requests for Domain operations
- `app/Actions/Platform/ResolveCompanyByHostnameAction.php` + `app/Http/Middleware/InitializeTenancyByResolvedDomain.php` (OD-3)
- Tests across Provisioning / Domains / Resolution / Security

**Modify**
- `docs/database/platform.dbml` — add `domains` table + refs.
- `.kiro/steering/*` / docs status — only if steering explicitly permits; not part of code.
- Possibly `config/tenancy.php` `domain_model` cleanup (optional, non-breaking).

**Do not touch:** `Company`, `CompanyStatus`, `CompanyIsStanclTenant`, Stancl event pipelines
(keep empty), Platform RBAC, `CompanyService` create semantics (provisioning is separate).

---

## Risks & notes

- Redis/Horizon workers and failed-job retry paths are **unproven** (spike doc §G/§J). The
  queued provisioning entry point (OD-4) is tested under the sync driver; production queue
  robustness is a follow-up verification, not a guarantee.
- MySQL DDL is non-transactional: partial failure is real; OD-1's delete-and-recreate-on-retry
  handles it.
- `database/migrations/tenant` is referenced by `config/tenancy.php` but does not exist yet;
  creating it is part of this phase.
