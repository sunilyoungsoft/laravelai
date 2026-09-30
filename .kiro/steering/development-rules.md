---
inclusion: always
---

# Development Rules

These are the stable application-layer and process rules. The worked proof lives in
`Modules/Demo` and is described in `docs/architecture/application-layer.md`. Read that
doc for the concrete example; this file states the rules that must always hold.

`Modules/Demo` is architecture proof only. `DemoNote` is not CRM data and its
platform-connection storage must not be copied into real modules.

## Application layer: Action-first

Every meaningful business/application operation is an **Action**. Web, API, future
AI/MCP, and CLI/jobs are entry points that converge on the **same** Action. Never
duplicate business logic across entry points.

```
Entry point → boundary validation → DTO → Action → models/domain services
            → explicit transaction (if atomicity needed) → commit → events/jobs
```

## DTOs

- Typed, readonly PHP classes carrying application input.
- May do light normalization only.
- Do NOT own: HTTP authorization, HTTP validation rules, persistence, tenant switching, or business workflows.
- Do NOT build a universal DTO framework or a `DataTransferObjectInterface` until a real repeated need appears.

## Actions

- Represent one meaningful operation (e.g. `CreateCustomerAction`, `PostInvoiceAction`).
- May use models, domain/business services, DB transactions, and events.
- Must not hard-code tenant DB names or trust client-supplied `company_id`.
- Must not assume the caller is authorized — the entry layer authorizes first.
- Do NOT create a `BaseAction` that wraps every Action in `DB::transaction()`. Transactions are explicit, only where the operation requires atomicity.
- Do NOT add repository/service layers unless there is a real, shared responsibility. No layers for their own sake.

## Entry points

**Web / Inertia:** React → thin Inertia controller → Form Request → DTO → Action → DB → Inertia response/redirect. The React web app must not call the REST API for normal web operations.

**REST API:** exists for mobile, external integrations, and third parties. API request → API validation → DTO → same Action → API Resource → JSON. Production convention is versioned `/api/v1/...`. The Demo route `/api/demo-notes` is proof only, not the final API design.

**AI / MCP:** future. Do not install Laravel MCP yet. When added: authenticate → resolve tenant context → authorize → validate tool args → DTO → Action. AI must never bypass authorization and must not touch models/DB directly.

## Authorization & tenant context

- Authorize server-side before executing the business operation. Frontend checks are UX only.
- Never trust client-provided `company_id`, tenant ID, or database name. Tenant context comes from authenticated/resolved context.
- Web, API, and AI may authenticate differently but must converge on the same authorized Action.

## Packages

Do not add a package just because it exists. Each package needs a defined single
responsibility, compatibility with this architecture, and a reason to adopt.
Principle: **one responsibility → one owner**; avoid overlapping sources of truth.

Do not, without a separate approved evaluation: replace Platform RBAC with Spatie
Permission, introduce `nwidart/laravel-modules`, or enable Stancl's `CreateDatabase`
provisioning jobs.

## Modules

Future ERP modules are self-contained under `Modules/{ModuleName}` (e.g. `Actions/`,
`Data/`, `Models/`, `Http/Controllers/` + `Http/Controllers/Api/`, `Http/Requests/`,
`Resources/`, `Events/`, `Policies/`, `Services/`, `routes/`, `Database/`). Create only
folders actually needed. Use a `Service` only for a real service responsibility.

## Development process

Before implementing any significant feature:

1. Inspect existing code and read the relevant architecture doc.
2. Identify reusable existing functionality; check if a package already solves the infrastructure problem.
3. Write a plan/spec (a Kiro Spec: requirements → design → tasks) and list files that will change and architectural risks.
4. Implement the smallest clean solution.
5. Run tests and Pint. Report exactly what changed.

Do not make broad architectural changes from a single prompt. If a requirement
conflicts with the existing architecture, STOP and explain the conflict.

## Testing & quality gates (do not weaken)

- Completed phases and their tests must keep passing. Do not remove or weaken existing tests to make a new feature pass.
- Run the suite with PHP 8.4 (`php artisan test`) and `vendor/bin/pint --test` before reporting done.
- Financial values never use float — use decimal / string / a Money value object.
