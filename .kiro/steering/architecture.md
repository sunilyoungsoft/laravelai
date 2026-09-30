---
inclusion: always
---

# Core Architecture

This is a modular, multi-tenant SaaS ERP/CRM platform built on Laravel 13 / PHP 8.4.
(The product name is not decided yet — do not hardcode a product name anywhere in code or config.)

Authoritative design records (read these before changing architecture — do not duplicate them here):

- Application layer pattern: `docs/architecture/application-layer.md`
- Tenancy spike findings + decisions: `docs/architecture/tenancy-spike.md`
- Platform schema: `docs/database/platform.dbml`

## Two-database model

```
Laravel
  ├── PLATFORM  → platform_db (connection: platform)
  │     Platform Users, Platform RBAC, Companies, Domains,
  │     Subscriptions, Module management, platform settings
  └── WORKSPACE → workspace_{lowercase_company_ulid} (connection template: workspace)
        Workspace Users, Workspace RBAC, Customers, Leads, Invoices,
        Inventory, HR, CRM and other ERP business data
```

- Platform data lives in the Platform DB only. Business/customer ERP/CRM data lives in Workspace DBs only.
- Never store real CRM/ERP customer data in the Platform DB.
- Platform models, migrations, seeders, and services set `$connection = 'platform'` explicitly. Do not rely on Laravel's default connection.

## Application layer (the core pattern)

One business operation. Multiple entry points. No duplicated business logic.

```
Entry: Web/Inertia | REST API | AI/MCP (future) | CLI/Jobs
  → Boundary validation (Form Requests / future tool schemas)
  → Typed readonly DTO
  → Action (the business operation)
  → Models / domain services
  → Explicit DB transaction (only when atomicity is required)
  → Commit → events / jobs / external side effects
```

Rules and responsibilities for DTOs, Actions, transactions, and authorization
live in `development-rules.md`. That file is the source of truth for the
application layer. Where an older rule says "prefer services over actions,"
the application layer here is **Action-first**; use a service only for a real
shared domain responsibility, never as an automatic wrapper.

## Architecture principles

- Keep the Core (`app/`) stable. Business functionality lives in modules under `Modules/{ModuleName}`.
- Modules stay independently maintainable. Prefer events/contracts over direct module-to-module calls.
- Controllers (web and API) stay thin: validate at the boundary, build a DTO, call an Action, return a response.
- Use dependency injection. Apply SOLID where it provides real value. Do not add patterns for their own sake.
- No microservices.

## Tenancy ownership (Stancl is infrastructure)

Tenancy uses `stancl/tenancy` as the infrastructure layer only (see tenancy-spike doc). Boundaries already validated:

- `Company` is the tenant business entity; Company ULID is the tenant key. There is no separate Stancl `tenants` table.
- Stancl owns: context initialize/end, dynamic `tenant` connection switching and revert to `platform`, and queue `tenant_id` payload + job-time re-init.
- The application owns: `Company` registry, `database_name` generation (`workspace_{lowercase_ulid}`), Company lifecycle, Platform RBAC, and future Domains / provisioning / subscriptions / Workspace users.
- The `platform` connection is permanently central and is never rewritten by Stancl.

## Before changing architecture

1. Inspect existing implementation and the docs above.
2. Understand dependencies and existing conventions.
3. Avoid breaking completed phases or existing modules.
4. Explain significant architectural changes before implementing them.
5. If a requirement conflicts with the established architecture, STOP and report the conflict. Do not silently redesign.
