---
inclusion: always
---

# Code Quality

Follow:

- SOLID
- DRY
- clear naming
- dependency injection
- single responsibility
- separation of concerns

## Controllers

Controllers should be thin.

Bad:

Controller contains:

- validation
- business rules
- database queries
- external API calls
- notifications

Prefer:

Controller
    ↓
Request validation (Form Request)
    ↓
DTO
    ↓
Action
    ↓
Models / domain services

This project is **Action-first**: a meaningful business/application operation is an
Action, and Web/API/AI/CLI entry points converge on the same Action. See
`development-rules.md` (authoritative for the application layer) and `architecture.md`.
Use a Service only for a genuine shared domain responsibility, not as an automatic
wrapper around a single Action.

## Models

Models should represent persistence and relationships.

Do not turn models into giant business-logic containers.

## Services

Use a Service when logic is reused across multiple Actions or represents
a cohesive domain responsibility that should not belong to a single Action.

Do not create a Service merely to wrap an Action or a trivial database call.

Actions carry the business/application operations (see `development-rules.md`).

Services are for a genuine shared domain responsibility reused across Actions.
Avoid services that only wrap one trivial database call, and do not add a
service layer just to have one.

Do not create a `BaseAction` that wraps every Action in a transaction —
transactions are explicit where atomicity is required.

## Repositories

Do not create repositories automatically for every model.

Use them when they provide meaningful abstraction or infrastructure separation.

## Naming

Use descriptive names.

Prefer:

InvoiceService
CreateInvoice (service method)

over:

InvoiceHelper

Prefer:

ResolveTenant

over:

CommonHelper

Avoid generic classes such as:

- Helper
- Utility
- Manager

unless their responsibility is genuinely broad and well-defined.
Do not create a custom TenantManager for tenant initialization,
database switching, or tenant context restoration; those responsibilities
are owned by Stancl tenancy infrastructure.

Tenancy note: tenant context initialization/end and database connection switching are
owned by `stancl/tenancy` (see `tenancy.md`). Do not build a custom `TenantManager` for
those responsibilities. Application-owned tenancy concerns (Company registry,
`database_name` generation, lifecycle, provisioning, Domains) belong in appropriately
named Actions/Services, not a catch-all manager.

## Refactoring

Before refactoring:

1. Understand existing behavior.
2. Check tests.
3. Identify dependencies.
4. Make the smallest safe change.
5. Run tests.