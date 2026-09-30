---
inclusion: always
---

# Multi-Tenancy Rules

This application uses:

One Master Database
+
One separate database per company/tenant.

## Master Database

The Master Database contains platform-level information only.

Examples:

- companies
- users
- company_users
- plans
- subscriptions
- domains
- modules
- company_modules
- licenses
- platform settings

Never store tenant business data in the Master Database.

## Tenant Database

Tenant databases contain company-specific business data.

Examples:

- customers
- employees
- tasks
- sales
- purchases
- invoices
- inventory

## Tenant Context

Every tenant operation must execute inside a valid TenantContext.

Never execute tenant business queries without tenant context.

Never silently fall back to the Master Database.

Missing tenant context must fail safely.

## Database switching (owned by Stancl)

Tenancy infrastructure is `stancl/tenancy` (see `docs/architecture/tenancy-spike.md`).
Stancl owns:

- tenant context initialize / end
- loading the tenant database connection configuration
- switching the default connection to the dynamic `tenant` connection while initialized
- reconnecting / restoring the default connection back to `platform` on end
- injecting the tenant key into queued job payloads and re-initializing at job time

Do not build a custom `TenantManager` or manually switch databases in controllers,
services, or actions for these responsibilities. Enter tenant context through Stancl
(e.g. `tenancy()->initialize($company)` / `tenancy()->end()` or the provided run helper).

The application owns (not Stancl):

- `Company` as the tenant/SaaS customer registry
- `database_name` generation (`workspace_{lowercase_company_ulid}`)
- Company lifecycle / status
- future Domains, provisioning (create/migrate/drop workspace databases), subscriptions,
  and Workspace users/RBAC

The `platform` connection is permanently central and must be named explicitly on Platform
models/queries, because while tenancy is initialized the default connection is `tenant`.

## Security

Company A must never access Company B data.

Tenant isolation must be tested.

This applies to:

- HTTP requests
- API requests
- queues
- events
- listeners
- notifications
- scheduled jobs
- console commands

## Jobs

Every tenant-specific queued job must preserve enough information to restore TenantContext.

Never assume the original HTTP request context exists inside a queue worker.