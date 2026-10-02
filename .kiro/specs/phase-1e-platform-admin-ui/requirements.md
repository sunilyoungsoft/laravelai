# Phase 1E — Platform Admin UI — Requirements

> **Status: DRAFT for approval.** Spec-first. No application code until `requirements.md` and
> `design.md` are approved and `tasks.md` is accepted. Follow the Action-first, explicit
> `platform` connection, Stancl-owned-tenancy, and server-authoritative-authorization rules in
> steering. This phase adds **UI only**; it must not change the Phase 1D tenancy/domain/
> provisioning backend.

## 1. Purpose

Build the **Platform admin area** — an Inertia + React + shadcn/ui interface for Platform
administrators to manage Companies and trigger their Workspace provisioning. It is the first
real UI on top of the completed Platform backend (Phases 1B–1D).

## 2. Scope (in)

- Platform login (session auth on the existing `platform` guard) and logout.
- Platform layout with a sidebar and authenticated shell.
- Dashboard with Company status counts.
- Companies list: search + status filter; excludes soft-deleted by default.
- Create Company (no provisioning side effect — Platform record only).
- Company Details: Company information + a Workspace card + a single effective-Domain card.
- Synchronous Provision / Retry endpoint delegating to the existing `ProvisionWorkspaceAction`.
- Add / change the single effective domain via the existing Domain Actions.
- Consistent loading / empty / error / success states.
- Authorization-aware UI backed by a server-side `CompanyPolicy` (UI reflects, server enforces).

## 3. Scope (out / non-goals)

- No Workspace-side or CRM/ERP UI of any kind.
- No change to the Phase 1D backend: `ProvisionWorkspaceAction`, `WorkspaceDatabaseService`,
  Domain Actions, `Domain`/`Company` models, enums, middleware, migrations stay as-is.
- No change to the multi-domain / `is_primary` **schema**. The UI exposes only one effective
  (primary) domain per company (see 1E-C). No domain list, no "set primary", no multi-domain UI.
- No 2FA, no registration, no password reset, no password-change UI (see 1E-A).
- No queue/async provisioning in this phase (see 1E-E); the UI still models provisioning as a
  lifecycle state so async can be added later without a UI redesign.
- No DNS/SSL automation or custom-domain verification transport.
- No product-name hardcoding (name undecided); use the shared `appName` / neutral wording.

## 4. Confirmed decisions

- **1E-A — Minimal Platform auth.** Session login/logout only, on the existing `platform`
  guard. No 2FA, registration, or password reset.
- **1E-B — Authorization.** A server-side `CompanyPolicy` plus a provisioning authorization
  check, both keyed off the **Admin system role** (`slug=admin`, `is_system=true`). The server
  is authoritative; the UI reflects permissions via Inertia-shared `auth.can`.
- **1E-C — Single effective domain.** Do not change the 1D multi-domain/`is_primary` schema. The
  UI shows exactly **one** effective domain (the primary). "Add Domain" when none exists;
  "Change Domain" replaces the effective one via the existing Actions. No list / set-primary /
  multi-domain UI.
- **1E-D — Tooling (Stage 0).** Initialize shadcn/ui, confirm TS config + path aliases (`@/*`
  already in tsconfig + vite), and add ESLint/Prettier plus lint/type-check scripts. No broad
  upgrades. Keep `typescript@^7.0.2` and `@types/node@^26.6.1` as-is — correct/current, not to
  be downgraded. Only **add** shadcn + lint dependencies.
- **1E-E — Synchronous provisioning.** The provisioning endpoint runs synchronously (no Redis/
  queue): a thin controller calls `ProvisionWorkspaceAction`. The UI still models provisioning
  as a lifecycle state so async can be layered on later.

## 5. Functional requirements & acceptance criteria

### A. Authentication (1E-A)

- A1. An unauthenticated visitor to any Platform admin route is redirected to the login page.
- A2. A Platform User can log in with email + password against the `platform` guard; session is
  established and they land on the Dashboard.
- A3. Invalid credentials return a validation error without revealing which field was wrong.
- A4. Logout ends the session and redirects to login. Session is regenerated on login and
  invalidated on logout (standard Laravel session safety).
- A5. No registration, password-reset, or 2FA surfaces exist.

### B. Authorization (1E-B)

- B1. `CompanyPolicy` governs `viewAny`, `view`, `create`, and a `provision` ability (and domain
  management for a company); each returns true only for a Platform User holding the active Admin
  system role.
- B2. Every controller action authorizes via the policy **before** executing business work.
  Removing a button in the UI never substitutes for the server check.
- B3. Inertia shares `auth.user` and `auth.can` so the UI can hide/disable actions the user is
  not allowed to perform; a forbidden request still returns 403 server-side.

### C. Dashboard

- C1. Shows counts of Companies grouped by `CompanyStatus` (pending, provisioning, active,
  provisioning_failed, suspended, deactivated).
- C2. Counts exclude soft-deleted Companies.
- C3. Empty state when there are no companies.

### D. Companies list

- D1. Lists Companies with name, slug, status badge, and effective-domain summary.
- D2. Free-text search (name/slug) and a status filter; both reflected in the URL (shareable).
- D3. Soft-deleted Companies are excluded by default.
- D4. Paginated. Loading and empty states are handled.

### E. Create Company

- E1. A form captures Company profile fields accepted by `CompanyService::create`
  (name, slug, contact fields, address). System fields are never exposed.
- E2. Submitting creates a Platform Company record **only** — status `pending`, **no**
  provisioning side effect, no Workspace database.
- E3. Server validation errors (e.g. duplicate/reserved slug) render against the right fields.
- E4. On success, redirect to the new Company's Details page with a success flash.

### F. Company Details

- F1. **Company information** card shows profile fields and metadata (status, created, etc.).
- F2. **Workspace** card shows provisioning lifecycle state derived from `CompanyStatus` and the
  `database_name`, with the correct primary action:
  - `pending` → "Provision" ; `provisioning_failed` → "Retry" ; `provisioning` → in-progress,
    no trigger ; `active` → provisioned, no trigger ; `suspended`/`deactivated` → no trigger.
- F3. Triggering Provision/Retry calls the synchronous endpoint → `ProvisionWorkspaceAction`;
  success and failure both surface clearly, and the card reflects the resulting status.
- F4. **Domain** card shows the single effective (primary) domain, or an empty state with
  "Add Domain" when none exists; when one exists, offer "Change Domain".
- F5. Add/Change delegate to `CreateDomainAction` (+ `SetPrimaryDomainAction` as the existing
  Actions require) and surface normalization/uniqueness validation errors.

### G. UX states (cross-cutting)

- G1. Every data fetch has a loading state; every list/section has an empty state.
- G2. Every mutating action has pending (disabled/spinner), success (flash/toast), and failure
  (inline error) states. Destructive/irreversible actions use a confirm dialog.

## 6. Backend reuse (must not duplicate or modify)

| UI need | Existing backend (reuse as-is) |
|---------|--------------------------------|
| Create Company | `App\Services\Platform\CompanyService::create()` |
| Provision / Retry | `App\Actions\Platform\ProvisionWorkspaceAction::execute()` |
| Add domain | `App\Actions\Platform\Domains\CreateDomainAction::execute()` |
| Make/replace effective domain | `App\Actions\Platform\Domains\SetPrimaryDomainAction::execute()` |
| Activate platform subdomain | `App\Actions\Platform\Domains\ActivateSubdomainAction::execute()` |
| Auth identity | `platform` guard + `App\Models\PlatformUser` |
| Admin-role authorization | `PlatformUser->roles()` where `slug=admin`, `is_system=true` |

## 7. Quality gates

- `php artisan test` (PHP 8.4) stays green including a new Phase 1E auth/policy/controller suite;
  do not weaken existing tests.
- `vendor/bin/pint --test` passes.
- New lint/type-check scripts pass; `npm run build` succeeds.
- Server-side authorization is proven by negative tests (non-admin / unauthenticated → 403/redirect).
