# Application Layer Architecture

Status: **architecture proof complete** (Demo module only; not CRM/ERP).

This document describes the reusable application/business-layer pattern established by the `Modules/Demo` proof.

## Principle

One business operation. Multiple entry points. No duplicated business logic.

```text
ENTRY LAYERS
  Web / Inertia
  REST API
  AI / MCP (future)
  CLI / Jobs

        ↓

BOUNDARY VALIDATION
  Form Requests / Tool schemas

        ↓

APPLICATION DATA
  Typed readonly DTOs

        ↓

APPLICATION ACTIONS
  Business operations

        ↓

MODELS / DOMAIN SERVICES

        ↓

EXPLICIT DATABASE TRANSACTIONS (when atomicity is required)

        ↓

COMMIT → events / queues / external side effects
```

## Proof implemented

| Piece | Location |
|-------|----------|
| DTO | `Modules/Demo/Data/CreateDemoNoteData.php` |
| Action | `Modules/Demo/Actions/CreateDemoNoteAction.php` |
| Model | `Modules/Demo/Models/DemoNote.php` |
| HTTP Request | `Modules/Demo/Http/Requests/StoreDemoNoteRequest.php` |
| Web controller | `Modules/Demo/Http/Controllers/DemoNoteController.php` |
| API controller | `Modules/Demo/Http/Controllers/Api/DemoNoteApiController.php` |
| Web UI | `resources/js/Pages/Demo/Notes/Create.tsx` |

**DemoNote disclaimer:** `demo_notes` lives on the **platform** connection only as a temporary architecture sample. It is **not** product CRM/ERP data. Real Customers and other business entities belong in **Workspace** databases after tenancy provisioning. Do not copy this platform storage choice into real modules.

Automated tests use the existing platform testing DB safety conventions (`laravelai_platform_testing` + `RefreshDatabase`). This proof does **not** require migrating a live/production Platform database.

## Web flow

```text
React (Inertia)
  → DemoNoteController (thin)
  → StoreDemoNoteRequest (HTTP validation)
  → CreateDemoNoteData
  → CreateDemoNoteAction
  → DemoNote / DB
  → redirect / flash
```

Routes: `GET /demo-notes/create`, `POST /demo-notes`.

## API flow

```text
HTTP JSON
  → DemoNoteApiController (thin)
  → StoreDemoNoteRequest (same Request in this proof)
  → CreateDemoNoteData (same DTO)
  → CreateDemoNoteAction (same Action)
  → DemoNoteResource
  → JSON 201
```

**Temporary proof route:** `POST /api/demo-notes`.

**Future production convention:** versioned APIs under `/api/v1/...`.

## Future AI / MCP flow (not implemented)

```text
MCP Tool
  → authenticate identity
  → resolve Workspace / tenant context
  → authorize
  → validate tool arguments
  → CreateDemoNoteData (same DTO)
  → CreateDemoNoteAction (same Action)
  → result for the agent
```

Do not install Laravel MCP until that platform is intentionally started. AI must not bypass authorization.

## Responsibilities

### DTO

- Typed application input contract
- Light normalization only when appropriate
- No HTTP auth, no HTTP validation rules, no persistence, no tenancy switching

### Form Request (HTTP boundary)

- Required fields, formats, constraints
- Authorization at the HTTP boundary where appropriate
- Converts validated input into the DTO

This proof shares `StoreDemoNoteRequest` between Web and API for simplicity. **Production Web and API boundaries may use separate Request classes** while still sharing the same DTO and Action.

### Action

- Meaningful business/application operation
- May use models, transactions, events
- Must not hard-code tenant DB names or trust client `company_id`
- Must not assume the caller is authorized — entry layers authorize first

### Transactions

- Explicit at the operation boundary when atomicity is required
- **No** BaseAction that wraps every Action in `DB::transaction()`
- Side effects that must not run on rollback should happen after commit (events/jobs)

### Authorization

- Separate from DTO and from Action execution
- Web / API / AI each authenticate and authorize before calling the Action
- This Demo proof intentionally allows open access; real modules use Policies/Gates

### Tenancy boundary

- Not implemented in this proof
- Compatible with Platform DB + Workspace DB + Stancl
- Workspace context must come from resolved/authenticated tenant context, never from client-supplied company/database identifiers

## Module loading

- PSR-4: `Modules\` → `Modules/`
- Manual `DemoServiceProvider` (no nwidart/laravel-modules in this proof)
- Provider loads module migrations and web/api routes

## What remains custom vs packages later

| Concern | Owner now | Later candidates |
|---------|-----------|------------------|
| Application Actions/DTOs | Custom module code | Stay custom |
| HTTP validation | Laravel Form Requests | Stay Laravel |
| Tenancy switching | Deferred (Stancl spike evaluated) | Stancl infrastructure |
| Permissions | Platform RBAC exists; module Policies later | Evaluate Spatie separately |
| Module discovery | Manual providers | Evaluate nwidart separately |
| AI tools | Documented only | Evaluate Laravel MCP separately |

## Related docs

- [`docs/architecture/tenancy-spike.md`](tenancy-spike.md)
- [`docs/database/platform.dbml`](../database/platform.dbml) — product Platform schema (Demo table intentionally omitted)
