# Phase 1D — Workspace Provisioning + Domains — Requirements

## Introduction

Phase 1D adds two related capabilities on top of the completed tenancy spike:

1. **Workspace database provisioning** — creating the *physical* Workspace database for a
   Company, running Workspace migrations against it, verifying it, and moving the Company
   through its lifecycle to `active` (or `provisioning_failed` on error).
2. **Platform-owned Domains** — an application-owned `domains` table on the Platform database
   that maps hostnames to Companies, so an incoming hostname can be resolved to a Company and
   used to initialize tenancy through Stancl, including the HTTP middleware that performs this.

These requirements describe observable system behavior and acceptance criteria only.
Implementation detail lives in `design.md`.

> **Open decisions OD-1…OD-5 are CONFIRMED.** Their confirmed content is recorded in
> `tasks.md` section 0 and reflected throughout these requirements (see the notes tagged
> `[OD-n confirmed]`).

### Established architecture this Spec must preserve (not re-decide)

- One Platform DB + one Workspace DB per Company. `Company` is the business entity **and**
  the Stancl tenant entity. There is **no** separate Stancl `tenants` table.
- Stancl owns tenant context init/end, dynamic `tenant` connection switching, restoring the
  central `platform` connection, and queue tenant-context propagation. No custom
  `TenantManager`; no manual DB switching in controllers/Actions/Services.
- `companies.database_name` (`workspace_{lowercase_company_ulid}`) is application-owned and
  authoritative. Do not introduce another naming convention. Do not enable Stancl's default
  `CreateDatabase` pipeline on Company creation.
- The `platform` connection is permanently central; Platform models/queries name it
  explicitly because the default connection becomes `tenant` while tenancy is initialized.
- `CompanyStatus` already defines: `pending`, `provisioning`, `active`,
  `provisioning_failed`, `suspended`, `deactivated`. Reuse it; do not create a duplicate
  lifecycle system.
- Application layer is **Action-first**. Services only for genuine shared responsibilities.
  No generic Managers/Helpers/Repositories/Interfaces/BaseAction. Transactions are explicit.

### Terminology

- **Provisioning** — creating and preparing the physical Workspace database for a Company.
- **Workspace migrations** — migrations that run against a Workspace (tenant) database, kept
  separate from Platform migrations.
- **Domain** — a Platform-owned record mapping a hostname to a Company.
- **Primary Domain** — the single canonical Domain for a Company.

---

## Part A — Workspace Provisioning

### Requirement A1 — Provision a Workspace database for a pending Company

**User story:** As a Platform operator, I want to provision a Company's Workspace database so
that the Company gains an isolated, migrated, usable Workspace and becomes `active`.

#### Acceptance Criteria

1. WHEN provisioning starts for a Company whose status is `pending` THEN the system SHALL set
   the Company status to `provisioning` before creating the physical database.
2. WHEN the Company is in `provisioning` THEN the system SHALL create a physical database
   named exactly `companies.database_name` (`workspace_{lowercase_company_ulid}`).
3. WHEN the physical database exists THEN the system SHALL run all Workspace migrations
   against that database and no other.
4. WHEN Workspace migrations complete THEN the system SHALL verify the Workspace is usable
   (see A5) before changing status.
5. WHEN verification succeeds THEN the system SHALL set the Company status to `active`.
6. WHEN provisioning succeeds THEN the physical database name SHALL equal
   `companies.database_name` and the Platform `companies` row SHALL be unchanged except for
   status and audit fields.
7. The system SHALL record provisioning start, success, and failure via structured logs that
   include the Company ULID and `database_name`, and SHALL NOT log secrets.

### Requirement A2 — Failed provisioning never leaves a Company active

**User story:** As a Platform operator, I want a failed provisioning to be clearly marked so
that a broken Workspace is never treated as usable.

#### Acceptance Criteria

1. IF creating the physical database fails THEN the system SHALL set the Company status to
   `provisioning_failed` and SHALL NOT set it to `active`.
2. IF Workspace migrations fail THEN the system SHALL set the Company status to
   `provisioning_failed` and SHALL NOT set it to `active`.
3. IF verification fails THEN the system SHALL set the Company status to
   `provisioning_failed` and SHALL NOT set it to `active`.
4. IF provisioning times out THEN the system SHALL set the Company status to
   `provisioning_failed` and SHALL NOT set it to `active`. *(OD-1 confirmed)*
5. WHEN provisioning fails at any step THEN the system SHALL record the failure reason in a
   structured log including the Company ULID and the failing step.
6. Under no failure path SHALL a Company end in `active` without a verified, migrated
   Workspace database.

### Requirement A3 — Provisioning behavior is defined for every relevant Company status

**User story:** As a Platform operator, I want provisioning to behave predictably regardless
of the Company's current status so that I never trigger unsafe or ambiguous operations.

#### Acceptance Criteria

1. WHEN provisioning is requested for a `pending` Company THEN the system SHALL proceed with
   provisioning (A1).
2. WHEN provisioning is requested for a `provisioning_failed` Company THEN the system SHALL
   allow a retry (A4) and SHALL proceed under the same rules as A1.
3. WHEN provisioning is requested for a Company already `active` THEN the system SHALL
   **reject** the request with a clear error, SHALL NOT treat it as a silent success, and SHALL
   NOT re-create or re-migrate the database. There is no force/repair flow in Phase 1D.
   *(OD-2 confirmed)*
4. WHEN provisioning is requested for a `provisioning` Company THEN the system SHALL refuse to
   start a second concurrent provisioning run for the same Company (A6) and SHALL fail safely
   with a clear error. *(OD-1 confirmed)*
5. WHEN provisioning is requested for a `suspended` or `deactivated` Company THEN the system
   SHALL refuse to provision and SHALL fail safely with a clear error (these statuses are not
   valid provisioning entry points in Phase 1D).
6. WHEN provisioning is refused due to invalid status THEN the system SHALL NOT modify the
   Company status or the physical database.

### Requirement A4 — Retry after failure (delete + recreate)

**User story:** As a Platform operator, I want to safely retry provisioning after a failure so
that a transient error does not permanently block a Company, without reusing a partial
Workspace.

#### Acceptance Criteria

1. WHEN provisioning is retried for a `provisioning_failed` Company THEN the system SHALL move
   it back to `provisioning` and re-run the workflow.
2. WHEN a retry begins and a Workspace database already exists THEN the system SHALL DELETE the
   existing Workspace database first, then create a fresh database. The system SHALL NOT reuse
   a partially provisioned Workspace database. *(OD-1 confirmed)*
3. WHEN a retry succeeds THEN the Company SHALL become `active` exactly as in A1.
4. Retry SHALL only be permitted from `provisioning_failed` (not from `active`,
   `provisioning`, `suspended`, or `deactivated`). *(OD-1 confirmed)*

### Requirement A5 — Workspace verification

**User story:** As a Platform operator, I want provisioning to verify the Workspace before
marking it active so that `active` always means "usable".

#### Acceptance Criteria

1. WHEN verification runs THEN the system SHALL confirm the physical database exists and is
   reachable through the Stancl-provided tenant connection for that Company.
2. WHEN verification runs THEN the system SHALL confirm the Workspace migration state
   reflects a fully migrated Workspace (no pending Workspace migrations).
3. WHEN verification runs THEN it SHALL execute inside valid tenant context and SHALL NOT read
   or write the Platform database as a substitute for the Workspace.
4. IF any verification check fails THEN provisioning SHALL be treated as failed (A2).

### Requirement A6 — Idempotency and duplicate-execution safety

**User story:** As a Platform operator, I want provisioning to be safe against accidental
duplicate execution so that concurrent or repeated triggers cannot corrupt a Workspace.

#### Acceptance Criteria

1. The system SHALL prevent two provisioning runs for the same Company from both proceeding:
   a run may only start by atomically claiming the Company out of `pending`/`provisioning_failed`
   into `provisioning`; if the Company is already `provisioning`, the second run SHALL be
   refused. *(OD-1 confirmed)*
2. WHEN a retry encounters an existing physical database THEN the system SHALL delete and
   recreate it (A4.2), never silently reuse it.
3. Guarded deletion SHALL only ever target the Company's own `workspace_{ulid}` database, and
   SHALL refuse any name that is not that Company's `database_name`. *(OD-1 confirmed)*
4. A duplicate provisioning trigger for an `active` Company SHALL be rejected (A3.3).

### Requirement A7 — Guarded database deletion

**User story:** As a Platform operator, I want database deletion to be impossible to
mis-target so that provisioning cleanup can never drop the wrong database.

#### Acceptance Criteria

1. WHEN the system deletes a Workspace database THEN it SHALL verify the target name equals the
   Company's `database_name` and matches the `workspace_{26-char-lowercase-ulid}` pattern before
   issuing any `DROP`. *(OD-1 confirmed)*
2. IF the target name fails the guard THEN the system SHALL refuse to delete and SHALL raise a
   clear error.
3. The system SHALL NEVER delete the Platform database or any non-`workspace_` database.

---

## Part B — Domains

### Requirement B1 — Platform-owned Domains table

**User story:** As a Platform operator, I want Domains stored as Platform data so that
hostname-to-Company resolution happens before any tenant context is initialized.

#### Acceptance Criteria

1. The system SHALL store Domains in an application-owned `domains` table on the `platform`
   connection.
2. The system SHALL NOT use, publish, or migrate Stancl's stock `domains` or `tenants` tables.
3. Each Domain SHALL belong to exactly one Company (`company_id`).
4. The `domains` table SHALL include at least: `id`, `company_id`, `domain`, `type`,
   `is_primary`, `status`, `verified_at`, `created_by`, `updated_by`, `created_at`,
   `updated_at`, `deleted_at` (soft deletes), with the exact column/index details finalized in
   design.
5. `type` SHALL be one of `subdomain` or `custom`. `status` SHALL be one of `pending`,
   `active`, `inactive`, `verification_failed`. There SHALL be no separate `verified` status;
   verification is tracked by the separate `verified_at` timestamp.
6. Platform models, migrations, and services for Domains SHALL name the `platform` connection
   explicitly.

### Requirement B2 — Domain normalization and validation

**User story:** As a Platform operator, I want Domain values normalized and validated to
hostnames so that resolution is unambiguous and safe.

#### Acceptance Criteria

1. WHEN a Domain value is accepted THEN the system SHALL normalize it by: trimming whitespace,
   lowercasing, and removing a single trailing `.`.
2. The system SHALL validate that the normalized value is a syntactically valid hostname.
3. The system SHALL reject values that contain a protocol (e.g. `https://`), a path, a port,
   or a wildcard prefix (e.g. `*.`).
4. The system SHALL enforce hostname and label length constraints (max 253 chars total; each
   label 1–63 chars).
5. WHEN a value fails normalization/validation THEN the system SHALL reject it with a clear
   validation error and SHALL NOT persist it.

### Requirement B3 — Global Domain uniqueness with soft-delete identity reservation

**User story:** As a Platform operator, I want each hostname to belong to at most one Company
so that resolution can never be ambiguous.

#### Acceptance Criteria

1. A normalized Domain value SHALL be globally unique across all Companies.
2. Domain uniqueness SHALL continue to reserve the identity even when the Domain record is
   soft-deleted, consistent with the project's existing identity-reservation policy.
3. WHEN a caller attempts to create a Domain whose normalized value already exists (including
   soft-deleted) THEN the system SHALL reject it rather than creating a duplicate; reuse of a
   soft-deleted identity SHALL be by restoring the existing record.
4. A single Domain value SHALL never resolve to more than one Company.

### Requirement B4 — One primary Domain per Company, changed atomically

**User story:** As a Platform operator, I want exactly one primary Domain per Company so that
a Company has a single canonical hostname.

#### Acceptance Criteria

1. A Company MAY have multiple Domains.
2. At most one of a Company's Domains SHALL have `is_primary = true` at any time.
3. WHEN the primary Domain is changed THEN the demotion of the old primary and promotion of
   the new primary SHALL occur atomically (both-or-neither).
4. The single-primary invariant SHALL be enforced server-side and SHALL hold under concurrent
   requests; it SHALL NOT rely on UI/frontend validation alone.

### Requirement B5 — Platform-managed subdomains vs. custom domains

**User story:** As a Platform operator, I want platform-managed subdomains and customer custom
domains to follow appropriate activation rules so that only trusted hostnames go live.

#### Acceptance Criteria

1. A `subdomain` Domain SHALL be constructed/validated against a **configurable base domain**
   (supplied via config/env, never a hardcoded production domain) and MAY be activated through
   trusted platform logic. *(OD-5 confirmed)*
2. Subdomain labels SHALL reject reserved labels (`api`, `admin`, `www`, `platform`, and the
   rest of the existing reserved list). *(OD-5 confirmed)*
3. A `custom` Domain SHALL start in `pending` status and SHALL NOT be automatically activated
   in Phase 1D.
4. Phase 1D SHALL NOT include DNS provider integration, SSL/certificate automation, or full
   DNS verification infrastructure; these SHALL be documented as future work. *(OD-5 confirmed)*

### Requirement B6 — Domain audit fields

**User story:** As a Platform operator, I want to know who created/updated a Domain so that
Domain changes are auditable.

#### Acceptance Criteria

1. WHEN a Domain is created THEN the system SHALL record `created_by` as the acting Platform
   user.
2. WHEN a Domain is updated THEN the system SHALL record `updated_by` as the acting Platform
   user.
3. Domain audit foreign keys SHALL reference `platform_users` with `NO ACTION` on delete,
   consistent with existing Platform schema conventions.

---

## Part C — Tenant Resolution Boundary

### Requirement C1 — Resolve hostname to Company via Platform data, then initialize tenancy

**User story:** As the platform, I want to resolve an incoming hostname to a Company using
Platform data and then initialize tenancy through Stancl so that the correct Workspace is used.

#### Acceptance Criteria

1. WHEN a hostname must be resolved THEN the system SHALL look it up in the Platform `domains`
   table (normalized value) to find the owning Company, on the `platform` connection.
2. `ResolveCompanyByHostnameAction` SHALL resolve and return the owning Company only, and SHALL
   NOT initialize tenancy. *(OD-3 confirmed)*
3. The HTTP middleware (boundary) SHALL initialize tenancy for the resolved Company through
   Stancl's supported API (`tenancy()->initialize($company)`), never by manual DB switching,
   then allow the request to continue. *(OD-3 confirmed)*
4. Hostname resolution SHALL occur BEFORE tenant initialization, because Domain records are
   Platform data.
5. The system SHALL NOT resolve Domains by querying any Workspace database.
6. The system SHALL NOT use Stancl's stock Domains table for resolution.
7. WHEN a hostname is unknown, or maps to a Domain that is not in an active/resolvable state
   THEN resolution SHALL fail safely (no tenancy initialized, no Workspace access, clear
   error), and SHALL NOT fall back to the Platform DB for tenant business data.
8. A resolver SHALL only yield the Company that owns the resolved Domain; a Domain owned by
   Company A SHALL never resolve to or initialize Company B.
9. Client-supplied `company_id`, `tenant_id`, database name, or database identifier SHALL NOT
   select the Workspace. *(OD-3 confirmed)*

### Requirement C2 — HTTP middleware delivered in Phase 1D

**User story:** As a maintainer, I want the domain-identification middleware delivered in
Phase 1D so that hostname-based tenancy works end to end.

#### Acceptance Criteria

1. Phase 1D SHALL deliver the hostname → Company resolver Action and the HTTP middleware that
   initializes tenancy for the resolved Company. *(OD-3 confirmed)*
2. The middleware SHALL use `config('tenancy.central_domains')` for central-domain handling and
   SHALL be registered on the appropriate route group.

---

## Part D — Security & Isolation

### Requirement D1 — Cross-tenant and context safety

**User story:** As a security owner, I want Phase 1D to preserve strict tenant isolation so
that no cross-company access is possible.

#### Acceptance Criteria

1. Company A SHALL NOT be able to access Company B's Workspace data.
2. A Domain SHALL NOT resolve to multiple Companies (B3.4, C1.8).
3. Tenant business queries SHALL NOT execute without valid tenant context; missing tenant
   context SHALL fail safely with no silent fallback to the Platform DB.
4. Client input SHALL NOT select a Workspace database; the system SHALL NOT trust a
   client-supplied database name, `company_id`, or tenant identifier for choosing a Workspace.
5. Platform queries SHALL remain on the `platform` connection; Workspace queries SHALL occur
   only inside valid tenant context.
6. Provisioning SHALL only ever act on the Company it was invoked for and SHALL NOT activate a
   different Company.
7. Failed provisioning SHALL never leave a Company `active` (A2).
8. These guarantees SHALL be considered across HTTP requests, API requests, queues, events,
   listeners, notifications, scheduled jobs, and console commands where relevant to Phase 1D.

---

## Part E — Testing

### Requirement E1 — Tests are defined before implementation

**User story:** As a maintainer, I want Phase 1D behavior covered by automated tests so that a
feature is not considered complete until its tests pass.

#### Acceptance Criteria (test coverage the Spec commits to)

1. **Provisioning:** successful database creation; successful Workspace migration; Company
   becomes `active` only after successful provisioning; failed database creation →
   `provisioning_failed`; failed/timed-out Workspace migration → `provisioning_failed`; retry
   from `provisioning_failed` deletes and recreates the Workspace DB then reaches `active`;
   concurrent provisioning refused (status already `provisioning`); `active` provisioning
   rejected; guarded deletion refuses mis-targeted names; Workspace database identity equals
   `companies.database_name`; Platform DB unaffected by provisioning.
2. **Domains:** normalization; invalid-hostname rejection; global uniqueness; soft-deleted
   identity reservation; multiple Domains per Company; only one primary Domain; atomic primary
   replacement; subdomain vs. custom type behavior; configurable base domain; reserved-label
   rejection; status behavior.
3. **Tenant resolution:** hostname resolves to the correct Company; unknown Domain fails
   safely; a Domain owned by Company A cannot initialize Company B; the middleware initializes
   the resolved Company through Stancl; Platform stays central before and after tenant context;
   client cannot select the Workspace via request-supplied identifiers; Workspace data stays
   isolated.
4. **Entry points:** the Artisan Command and the Queue Job both invoke the same
   `ProvisionWorkspaceAction`; the Job resolves the Company on the `platform` connection.
5. Tests SHALL follow the existing test structure (`tests/Feature`, `tests/Unit`, plus a
   Security-focused group for negative isolation tests) and SHALL reuse the existing guarded
   Workspace-database test conventions rather than duplicating spike tests.
6. `php artisan test` (PHP 8.4) and `vendor/bin/pint --test` SHALL pass before Phase 1D is
   considered done, with the existing suite still green.

---

## Out of Scope (Phase 1D)

Workspace users/RBAC; subscriptions/billing; module runtime; AI/MCP; SSL automation; DNS
provider integration; full custom-domain verification infrastructure; Stancl stock Domains
table; Stancl stock Tenants table; automatic Stancl `CreateDatabase` pipeline; redesign of
Platform RBAC; redesign of Companies; ERP/business modules.

---

## Confirmed decisions (OD-1 … OD-5)

The full confirmed text is recorded in `tasks.md` section 0. Summary:

- **OD-1 — Provisioning failure/retry:** `provisioning` rejects concurrent runs; failure/timeout
  → `provisioning_failed`; retry only from `provisioning_failed`; on retry delete existing
  Workspace DB then create fresh (never reuse a partial DB); deletion is explicitly guarded;
  failed/partial provisioning never becomes `active`.
- **OD-2 — Active Company:** a provisioning request for an `active` Company is rejected (not a
  silent success); no reprovision; no force/repair in Phase 1D.
- **OD-3 — HTTP middleware:** implemented in Phase 1D; resolver resolves the Company only, the
  middleware initializes Stancl tenancy; no client-controlled Workspace selection; unknown
  hostname fails safely; Platform stays central.
- **OD-4 — Trigger:** both an Artisan Command and a Queue Job, both calling the same
  `ProvisionWorkspaceAction`; the Job carries the Company ULID and resolves on `platform`.
- **OD-5 — Subdomain/base domain:** base domain is configurable (no hardcoded production
  domain); subdomain validation/resolution uses that config; reserved labels stay unavailable;
  no DNS/SSL automation.
