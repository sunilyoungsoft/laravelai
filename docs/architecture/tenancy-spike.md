# Tenancy Architecture Spike — Stancl

Status: **complete** (architecture spike only; not production tenancy).

This document records findings from the Stancl integration spike. Recommendations below are based only on automated tests that passed in this repository.

## Test evidence

Commands run after implementation:

- `php artisan test` → **64 passed** (193 assertions), including `Tests\Feature\Tenancy\TenancySpikeTest` (4 tests, 43 assertions)
- `vendor/bin/pint --test` → **passed**

Spike tests proved:

1. Company ULID is the Stancl tenant key; `database()->getName()` equals `companies.database_name` (`workspace_{lowercase_ulid}`).
2. `DB::connection('platform')` stays on `laravelai_platform_testing` before, during, and after `tenancy()->initialize()` / `tenancy()->end()`.
3. Default connection becomes `tenant` only while initialized, then returns to `platform`.
4. Platform → Workspace A → Platform → Workspace B → Platform: marker rows do not leak across workspaces; workspace connections do not see `companies`; platform does not see workspace marker tables.
5. Sync-queue `SpikeWorkspaceProbeJob` dispatched under A/B resolves the matching workspace database and tenant key.

## A. Stancl version used

- `stancl/tenancy` **v3.10.1** (`composer.json` constraint `^3.10.1`)
- Transitive: `stancl/jobpipeline` v1.9.0, `stancl/virtualcolumn` v1.5.0, `facade/ignition-contracts` 1.0.2

## B. Laravel / PHP versions detected

- PHP **8.4.25**
- Laravel **v13.32.0**

## C. Company integration approach

**Company remains the business entity and is the Stancl tenant model.**

- `App\Models\Company` implements `Stancl\Tenancy\Contracts\TenantWithDatabase`.
- Trait: `App\Tenancy\Concerns\CompanyIsStanclTenant` (uses Stancl `HasDatabase` + `TenantRun` only).
- No `tenants` table, no `Stancl\Tenancy\Database\Models\Tenant` subclass, no `HasDataColumn` / `GeneratesIds` / `HasDomains`.
- `config('tenancy.tenant_model')` = `App\Models\Company::class`.
- `getInternal('db_name')` returns the existing `database_name` column.
- `setInternal()` is a no-op (Stancl must not rewrite Company columns).
- `internalPrefix()` = `tenancy_` so real company attributes are not treated as connection overrides.

## D. Database switching approach

- `tenancy.database.central_connection` = `platform`
- `tenancy.database.template_tenant_connection` = `workspace`
- Bootstrappers enabled: `DatabaseTenancyBootstrapper`, `QueueTenancyBootstrapper` only.
- Stancl creates a dynamic connection named `tenant` from the workspace template + Company `database_name`, sets it as default while initialized, and reconnects default to `platform` on `tenancy()->end()`.
- Explicit `DB::connection('platform')` is never rewritten by Stancl.

## E. Database naming approach

Unchanged application rule:

```text
workspace_{lowercase_company_ulid}
```

Stancl reads that value via `getInternal('db_name')`. Prefix/suffix in `config/tenancy.php` are empty and unused for naming. Company creation still does **not** create a physical database.

## F. Domain integration approach

**Not implemented in this spike (no Phase 1D).**

Stancl’s stock `domains` table / middleware were not published or migrated.

Intended later approach:

- Keep a custom Platform `domains` business table (`company_id`, `type`, `is_primary`, `status`, `verified_at`, audit, soft deletes).
- Resolve hostname → Company on Platform, then call `tenancy()->initialize($company)`.
- Do not require Stancl’s `domains` table for business domain records.

Checklist answer for Domains: **YES with caveat / approach only** (not proven by an integration test).

## G. Queue / context findings

- Proven with PHPUnit `QUEUE_CONNECTION=sync`.
- `QueueTenancyBootstrapper` injects `tenant_id` (Company ULID) into the job payload and re-initializes tenancy on `JobProcessing`.
- A and B jobs observed the correct workspace database names.
- Not proven: Redis queue workers, Horizon, long-running `queue:work` process reuse, or failed-job retry paths beyond sync.

## H. What Stancl should own

- Tenant context initialize / end
- Dynamic `tenant` connection switching and revert to central (`platform`)
- Queue payload `tenant_id` and job-time re-initialization
- Optional future identification middleware hooks (after custom Domain resolution)

## I. What the application should own

- `Company` as the SaaS customer / workspace registry
- Platform RBAC (`platform_users`, roles, permissions)
- `database_name` generation (`workspace_{ulid}`)
- Company lifecycle / status (`CompanyStatus`)
- Future Domains, subscriptions, modules, Workspace users/RBAC
- Provisioning (create/migrate/drop workspace databases) when that phase is designed
- Explicit `platform` connection usage on all Platform models/services

## J. Known limitations

- While tenancy is initialized, Laravel’s **default** connection is `tenant`. Code that omits an explicit connection can hit a workspace DB. Platform models already set `$connection = 'platform'`.
- Soft-deleted companies are invisible to `tenancy()->find()` unless `withTrashed()` is added later.
- In `local` environment, Stancl throws if the workspace database does not exist before initialize. Tests use `APP_ENV=testing` and create guarded DBs themselves.
- Cache / filesystem / Redis tenancy bootstrappers were intentionally disabled.
- Domain hostname identification is documented only, not tested.
- Sync queue proof is not a full production queue architecture proof.

## K. Upgrade risks

- Stancl v4 (docs site) is a different line; this spike used stable **3.10.x** for Laravel 13.
- Package defaults assume a `tenants` table and `TenantCreated` → `CreateDatabase` pipeline. Re-publishing the stock provider without emptying those jobs would break the “no provisioning on Company create” rule.
- Future package changes to `DatabaseConfig::getName()` / internal key conventions could require trait updates.
- Transitive `facade/ignition-contracts` is required by Stancl 3.10.1.

## L. Recommendation

**Proceed with Stancl** as the tenancy **infrastructure** layer, with these constraints already validated by tests:

- Company remains the tenant entity (no separate Stancl tenants table).
- Platform connection remains permanently central.
- Workspace DB names remain `workspace_{lowercase_ulid}`.
- Provisioning and Domains stay application-owned next phases; do not enable Stancl CreateDatabase jobs by default.

Do **not** treat this spike as production tenancy go-live. Next work (when approved) should design provisioning and Domains separately, without rewriting Platform RBAC or Companies.

## Acceptance checklist

| Question | Answer |
|----------|--------|
| Can Company remain our business entity? | **YES** |
| Can Company ULID remain the tenant identifier? | **YES** |
| Can `workspace_{company_ulid}` remain the DB naming strategy? | **YES** |
| Can platform_db remain permanently central? | **YES** |
| Can tenant DB switching happen safely? | **YES** |
| Can Workspace A/B remain isolated? | **YES** |
| Can tenant context be safely reset? | **YES** |
| Can queued jobs preserve tenant context? | **YES** (sync driver proven; Redis/Horizon workers **Not proven by this spike**) |
| Can our future custom Domains integrate with Stancl? | **YES with caveat** (documented approach; no Domains implementation/test) |
| Can Stancl remain infrastructure rather than business logic? | **YES** |

## Files introduced by the spike

- `config/tenancy.php`
- `app/Providers/TenancyServiceProvider.php`
- `app/Tenancy/Concerns/CompanyIsStanclTenant.php`
- `tests/Feature/Tenancy/TenancySpikeTest.php`
- `tests/Feature/Tenancy/SpikeWorkspaceProbeJob.php`
- `tests/Feature/Tenancy/Support/GuardedWorkspaceDatabase.php`
- `docs/architecture/tenancy-spike.md`

Modified: `composer.json` / `composer.lock`, `app/Models/Company.php` (trait only), `bootstrap/providers.php`, `phpunit.xml`, `README.md`.

Not modified: Company migration, CompanyService create behavior, Platform RBAC, `docs/database/platform.dbml`.
