# Phase 1D — Workspace Provisioning + Domains — Implementation Tasks

> **Status: Spec APPROVED and open decisions CONFIRMED.** `requirements.md` and `design.md` are
> approved; OD-1…OD-5 are confirmed (see section 0). No blocked tasks remain.
> Each task is small, ordered by dependency, and testable. Follow Action-first, explicit
> `platform` connection usage, Stancl-owned context switching, and existing conventions.

## Open-decision gate (blocking map)

| Decision | Status | Formerly blocked |
|----------|--------|------------------|
| OD-1 — provisioning failure/retry policy (delete + recreate on retry) | ✅ confirmed | 3.2, 4.1, 4.2 |
| OD-2 — active Company provisioning is rejected (no force/repair) | ✅ confirmed | 3.3 |
| OD-3 — implement HTTP tenant-resolution middleware in 1D | ✅ confirmed | 9.3 |
| OD-4 — both Command and Job trigger the same Action | ✅ confirmed | 5.1, 5.2, 5.3 |
| OD-5 — configurable base domain + reserved labels | ✅ confirmed | 8.3 |

## 0. Confirmed decisions (OD-1 … OD-5)

**OD-1 — Provisioning failure / retry policy.**
- A Company already in `provisioning` MUST reject a new provisioning action (no concurrent run).
- On failure or timeout, transition the Company to `provisioning_failed`.
- Retry is allowed only from `provisioning_failed`.
- On retry: if a Workspace database already exists, DELETE it first, then create a fresh
  database. Do NOT reuse a partially provisioned Workspace database.
- Database deletion MUST be explicitly guarded so it can only ever target the Company's own
  `workspace_{ulid}` database (never an unintended database).
- A failed/partial provisioning MUST never result in Company status `active`.

**OD-2 — Active Company.**
- If the Company is `active`, a new provisioning request MUST be rejected (raise a clear error).
- Do NOT silently treat it as success. Do NOT reprovision. No force/repair flow in Phase 1D.

**OD-3 — HTTP tenant-resolution middleware.**
- Implement the middleware in Phase 1D. Resolve incoming hostname via the application-owned
  Platform `domains` table on the `platform` connection → Company → initialize Stancl tenancy →
  continue the request.
- `ResolveCompanyByHostnameAction` resolves the Company only; it MUST NOT initialize tenancy.
  The middleware (HTTP boundary) initializes tenancy.
- Client-supplied `company_id` / `tenant_id` / database name / identifier MUST NOT select the
  Workspace. Unknown/unresolvable hostname fails safely. Platform connection stays central.

**OD-4 — Provisioning trigger.**
- Support BOTH an Artisan Command (manual/admin) and a Queue Job (async). Both call the same
  `ProvisionWorkspaceAction`; no duplicated provisioning logic.
- The Job carries the Company ULID and resolves the Company on the `platform` connection at run
  time. Preserve tenant-context rules; do not hand-roll Stancl tenant switching.

**OD-5 — Subdomain / base domain.**
- Production base domain is NOT finalized. Do NOT hardcode a real production domain. Make the
  base domain configurable (config/env). Subdomain validation/resolution uses that config.
- Reserved labels (`api`, `admin`, `www`, `platform`, …) remain unavailable. No DNS/SSL
  automation in Phase 1D.

## 1. Workspace schema foundation

- [x] 1.1 Create the `database/migrations/tenant/` directory (referenced by
  `config/tenancy.php` `--path` but missing) and add one minimal, reversible Workspace
  migration to prove per-Workspace migration works (e.g. a small `workspace_meta` table). No
  business/ERP tables. _Requirements: A3, A5, Database steering._
- [x] 1.2 Add a Unit test asserting the Workspace migration set is discoverable and that the
  path resolves. _Requirements: E1.1._

## 2. Provisioning infrastructure (Service)

- [x] 2.1 Add `app/Services/Platform/WorkspaceDatabaseService.php` with small, named methods:
  `databaseExists(Company)`, `createDatabase(Company)`, `dropDatabase(Company)` (guarded),
  `runWorkspaceMigrations(Company)`, `pendingMigrationCount(Company)`.
  **Correction (per instruction):** use the *configured* Stancl/database infrastructure and its
  supported APIs/primitives for create/delete/exists — resolve the configured tenant database
  manager through Stancl (`$company->database()->manager()`) rather than tightly coupling the
  service to the concrete `MySQLDatabaseManager` class. Do not hand-write `CREATE`/`DROP` SQL.
  Server-level DDL and the existence check run on the explicitly named `platform` connection
  (set the manager's connection to `platform`). `dropDatabase` is guarded so it can only target
  the Company's own `workspace_{ulid}` name (regex + name-equals-`database_name`). Never switch
  tenant connections manually (no `DB::setDefaultConnection`, no
  `config(['database.default' => ...])`); enter tenant context only via Stancl
  (`$company->run(...)` / `tenancy()->initialize()`) for migrations/verification.
  _Requirements: A1, A3, A5, A7, D1.5; Tenancy/DB steering; OD-1 guarded delete._
- [x] 2.2 Feature tests for `WorkspaceDatabaseService` using `GuardedWorkspaceDatabase`
  conventions: create → exists true; migrate → no pending migrations; DB name equals
  `companies.database_name`; Platform DB untouched. _Requirements: A1, A5, E1.1._

## 3. Provisioning workflow (Action + status guards)

- [x] 3.1 Add centralized, guarded status transitions on the `platform` connection: the
  `pending|provisioning_failed → provisioning` claim as a **conditional UPDATE** (affected-rows
  check) for concurrency safety, plus `→ active` and `→ provisioning_failed` transitions with
  audit (`updated_by`). _Requirements: A1.1, A2, A3.4, A6.1, D1.6._
- [x] 3.2 Add `app/Actions/Platform/ProvisionWorkspaceAction.php` orchestrating:
  `assertProvisionable → markProvisioning → freshDatabase → runWorkspaceMigrations →
  verifyWorkspace → markActive`, with a single boundary `catch` that logs the failing step,
  sets `provisioning_failed`, and rethrows. `freshDatabase` (OD-1): if the Workspace DB already
  exists (e.g. from a prior failed attempt), DELETE it via the guarded `dropDatabase` first,
  then create fresh — never reuse a partial DB. Status writes are small explicit `platform`
  transactions; DDL/migrations are outside transactions. _Requirements: A1, A2, A5, A7; OD-1;
  project-style orchestrator rules._
- [x] 3.3 Implement status-matrix behavior (pending→proceed, provisioning_failed→retry,
  provisioning→refuse concurrent run, active→**reject** with a clear error (OD-2, no no-op, no
  force), suspended/deactivated→refuse) with safe failures and no state change on refusal.
  _Requirements: A3; OD-2._
- [x] 3.4 Implement `verifyWorkspace` (DB exists, tenant connection reaches the right DB, zero
  pending Workspace migrations, all inside tenant context, no Platform substitution).
  _Requirements: A5._
- [x] 3.5 Structured logging at start/success/failure with Company ULID + `database_name`
  (never secrets). _Requirements: A1.7, A2.4; Logging steering._

## 4. Failure, retry, idempotency

- [x] 4.1 Implement the OD-1 retry policy: retry allowed only from `provisioning_failed`; on
  retry, delete any existing Workspace DB (guarded `dropDatabase`) then create fresh; never
  reuse a partial DB. Failure/timeout at any step → `provisioning_failed`, never `active`.
  _Requirements: A4, A6.2, A7; OD-1._
- [x] 4.2 Tests: failed `CREATE DATABASE` → `provisioning_failed`; failed migration →
  `provisioning_failed`; retry from `provisioning_failed` deletes the stale DB, recreates, and
  reaches `active`; concurrent provisioning (status already `provisioning`) is refused by the
  conditional-update guard; `active` re-provision is rejected (OD-2); guarded `dropDatabase`
  refuses a non-`workspace_{ulid}` / mismatched name. _Requirements: A2, A3, A4, A6, E1.1;
  OD-1, OD-2._

## 5. Provisioning entry points

- [x] 5.1 Add `app/Console/Commands/ProvisionWorkspaceCommand.php` — thin: resolve Company by
  id/slug on `platform`, call the same `ProvisionWorkspaceAction`, map result to exit code.
  _Requirements: A1; OD-4; thin-entry rules._
- [x] 5.2 Add `app/Jobs/Platform/ProvisionWorkspaceJob.php` carrying the Company ULID,
  re-resolving the Company on `platform` at run time, calling the **same**
  `ProvisionWorkspaceAction` (no duplicated logic); failure leaves `provisioning_failed`.
  _Requirements: A2, OD-4; queue notes._
- [x] 5.3 Tests: both Command and Job invoke the same Action and reach `active` on success;
  Job resolves the Company on `platform`. _Requirements: E1.1; OD-4._

## 6. Domain schema, enums, model

- [x] 6.1 Add `app/Enums/DomainType.php` (`subdomain`, `custom`) and
  `app/Enums/DomainStatus.php` (`pending`, `active`, `inactive`, `verification_failed`), mirroring
  `CompanyStatus` style. _Requirements: B1.5._
- [x] 6.2 Add `create_domains_table` migration on `platform`: columns per design; unique index
  on `domain` that reserves soft-deleted identity; indexes on `company_id`, `status`; FKs to
  `companies` and `platform_users` with `NO ACTION`; soft deletes; UTC (no `useCurrent`).
  _Requirements: B1, B3, B6; Database steering._
- [x] 6.3 Add `app/Models/Domain.php` (connection `platform`, HasUlids, SoftDeletes, casts,
  profile-only `$fillable` with system fields guarded, relations `company/creator/updater`).
  _Requirements: B1, B6._
- [x] 6.4 Add `database/factories/DomainFactory.php`. _Requirements: E1.2._
- [x] 6.5 Update `docs/database/platform.dbml` with the `domains` table and refs. _Requirements:
  Database steering (keep dbml in sync)._

## 7. Domain normalization, validation, uniqueness

- [x] 7.1 Implement hostname normalization (trim → lowercase → strip trailing `.`) and
  validation (reject protocol/path/port/wildcard; hostname syntax; length limits) at the Domain
  application boundary (DTO/Form Request + Action), not a generic helper. _Requirements: B2._
- [x] 7.2 Enforce global uniqueness with soft-delete reservation using
  `unique:platform.domains,domain` (no `withoutTrashed()`), reuse-by-restore for soft-deleted
  values. _Requirements: B3._
- [x] 7.3 Unit + Feature tests: normalization cases, invalid-hostname rejection, uniqueness
  incl. soft-deleted reservation. _Requirements: B2, B3, E1.2._

## 8. Domain Actions

- [x] 8.1 Add `CreateDomainAction` (+ DTO + Form Request): normalize/validate, enforce
  uniqueness, persist with audit, initial `status`/`type`; custom → `pending`. _Requirements:
  B1, B2, B3, B5, B6._
- [x] 8.2 Add `SetPrimaryDomainAction`: atomic demote-old/promote-new in a single `platform`
  transaction with row locking; server-side single-primary invariant. _Requirements: B4._
- [x] 8.3 Add trusted subdomain activation (Action or status transition) leaving custom domains
  `pending`; no DNS/SSL. Subdomain construction/validation uses a **configurable base domain**
  (config/env, not hardcoded) and rejects reserved labels (`api`, `admin`, `www`, `platform`,
  … — reuse the existing reserved list). _Requirements: B5; OD-5._
- [x] 8.4 Tests: multiple domains per company; only one primary; atomic primary swap (incl.
  concurrency); subdomain vs custom behavior; status transitions. _Requirements: B4, B5,
  E1.2._

## 9. Tenant resolution

- [x] 9.1 Add `app/Actions/Platform/ResolveCompanyByHostnameAction.php`: normalize hostname,
  look up Domain on `platform` only, guard resolvable state, return owning Company (does not
  initialize tenancy). Safe failure on unknown/unresolvable. _Requirements: C1, D1.2, D1.3._
- [x] 9.2 The tenancy initialization lives in the HTTP middleware (9.3), not in the resolver.
  The resolver returns the Company; the middleware calls `tenancy()->initialize($company)`.
  _Requirements: C1.2; OD-3._
- [x] 9.3 Implement domain-identification HTTP middleware (in Phase 1D) using the resolver +
  `tenancy()->initialize()` + `config('tenancy.central_domains')` for central-domain handling;
  register it on the appropriate route group. Client-supplied company/tenant/db identifiers must
  not select the Workspace; unknown hostname fails safely; Platform stays central.
  _Requirements: C1, C2; OD-3._
- [x] 9.4 Tests: hostname → correct Company; unknown → safe failure; A-domain cannot initialize
  B; resolved Company initializes via Stancl; Platform central before/after; Workspace isolated.
  _Requirements: C1, D1, E1.3._

## 10. Security / isolation tests

- [x] 10.1 Add `tests/Security/` (or `tests/Feature/Security/`) negative tests: Company A cannot
  read/modify Company B Workspace data; missing tenant context fails safely with no Platform
  fallback; client-supplied database/company/tenant id cannot select a Workspace; failed
  provisioning never yields `active`; domain resolution cannot cross companies. _Requirements:
  D1, E1; Testing/Security steering._

## 11. Verification & documentation

- [x] 11.1 Run `php artisan test` (PHP 8.4) and `vendor/bin/pint --test`; ensure the full
  existing suite stays green and Phase 1D tests pass. Do not weaken existing tests. _Requirements:
  E1.5; Testing steering regression order._
- [x] 11.2 Update project status/index docs to reflect Phase 1D delivery (only the docs the
  steering says to keep current, e.g. `docs/database/platform.dbml` already done in 6.5; note
  provisioning + Domains status). Record confirmed OD answers and any deferred middleware work.
  _Requirements: process rules._
- [~] 11.3 (OPTIONAL — NOT DONE) Non-breaking cleanup: neutralize `config('tenancy.domain_model')`
  reliance confusion (documented, since app Domain is independent of Stancl's stock domain
  model). Deferred: harmless as-is (the app never reads `tenancy.domain_model`); leave until a
  reason to touch tenancy config arises. _Requirements: B1.2._

---

## Suggested execution order (dependency-aware)

1 → 2 → 3 → 4 → 5 (provisioning vertical) then 6 → 7 → 8 (Domains vertical) then 9 → 10
(resolution + security) then 11 (verify/docs). Sections 1–5 and 6–8 are largely independent and
could proceed in parallel, but resolution (9) depends on Domains (6–8), and security tests (10)
depend on both verticals.
