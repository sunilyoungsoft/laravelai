---
inclusion: always
---

# Project Coding Style

Optimize every choice for one goal: code that is easy for a human to read, understand,
debug, test, and maintain. Follow **KISS** — prefer simple, explicit code over clever or
overly abstract code. Fewer lines is not the goal; understanding is.

When style and correctness conflict, correctness wins (see "Security & correctness first").

## Action-First Business Logic

This project is **Action-first**: a meaningful business/application operation is an Action
(e.g. `CreateCustomerAction`, `PostInvoiceAction`, `ProvisionWorkspaceAction`). Web, API,
AI, and CLI entry points converge on the same Action. `development-rules.md` is
authoritative for the application layer; this file covers coding style within it.

Controllers, Commands, Jobs, Listeners, and Routes must stay thin and delegate meaningful
work. Never place complex business logic directly in them.

```
Controller / Command
    → Form Request / boundary validation
    → DTO
    → Action
    → Models / domain services
```

Use a **Service** only for a genuine shared domain responsibility reused across Actions
(e.g. `NotificationService`, an external-integration service). It is not the default
container for every operation.

## Small Functions That Read as a Story

Keep functions small and single-purpose. The main method should orchestrate named steps
so the workflow is clear without reading every implementation detail.

```php
public function execute(): BackupResult
{
    $database = $this->selectDatabase();
    $backupFile = $this->takeBackup($database);
    $backupUrl = $this->uploadBackup($backupFile);

    return $this->saveBackupRecord($backupUrl);
}
```

Split a function when it does multiple unrelated things. A function should have one clear
reason to change. Push asynchronous side effects (PDF generation, email, audit log) into
listeners/jobs triggered by an event rather than inlining them.

There is no hard line limit, but if a method needs significant scrolling or mixes
unrelated responsibilities, split it.

## Naming

Names must explain intent: `selectTenantDatabase()`, `createBackupFile()`,
`saveBackupRecord()`. 
Avoid vague names such as `process()`, `doIt()`, `executeStuff()`, and
`helper()` when they do not communicate intent.

Framework-required method names such as Laravel's `handle()` are acceptable
when required by the framework contract.

Names such as `run()` or `execute()` are acceptable when the surrounding class
makes the operation clear.

## Readability Over Cleverness

Prefer several simple lines over a clever one-liner. Avoid dense chains
(`collect(...)->filter(...)->map(...)->reduce(...)`) when a plain loop is clearer.

## Avoid Deep Nesting

Use guard clauses, early returns, and small methods instead of nested `if` pyramids.

```php
if (! $tenant) {
    throw new TenantNotFoundException();
}

if (! $tenant->isActive()) {
    throw new TenantInactiveException();
}

return $this->process($tenant);
```

## Logging

Log meaningful business/application boundaries — Started, Completed, Failed — not every
small method.

```php
Log::info('Database backup completed', [
    'tenant_id' => $tenantId,
    'backup_id' => $backupId,
]);
```
Log meaningful business/application boundaries when operational visibility
requires it. Do not log every method call or routine operation.

For important operations, logging may include Started, Completed, and Failed
events as appropriate.

## Error Handling

Handle errors at the appropriate boundary. Never swallow exceptions with an empty `catch`.
Only catch when there is something meaningful to do; log with context and rethrow when you
cannot fully recover.

```php
try {
    // ...
} catch (\Throwable $exception) {
    Log::error('Database backup failed', [
        'tenant_id' => $tenantId,
        'error' => $exception->getMessage(),
    ]);

    throw $exception;
}
```

For important operations, follow: start → execute → log success, or on failure log the
error and handle/throw.

## Comments

Code explains **what**; comments explain **why**. Skip narrating obvious lines. Reserve
comments for non-obvious decisions.

```php
// Backup must run against the tenant database, never the platform database.
```

If code is hard to understand, improve the code before adding a comment.

## Keep Classes and Actions/Services Focused


Action-first does not mean one giant class. Keep Actions and shared Services
focused on one clear responsibility.

Split when responsibilities become unrelated.

For example, an Action such as `PostInvoiceAction` should not also contain
payment processing, PDF generation, file management, and notification logic.

If invoice calculation or payment logic is genuinely reused across multiple
Actions, it may belong in appropriately named shared domain services such as
`InvoiceCalculationService` or `InvoicePaymentService`.

Do not let a class become a dumping ground for database access, API calls,
notifications, calculations, file management, and unrelated business rules.

## Don't Over-Abstract

Do not auto-create an Interface, Repository, Factory, Strategy, Manager, Helper, Utility,
or Adapter for every class. Add an abstraction only when it solves a real problem:
multiple implementations, external integrations, testing boundaries, module boundaries, or
infrastructure isolation.

Avoid catch-all classes like `Helper.php`, `CommonHelper.php`, `Utility.php`,
`GlobalFunctions.php`. Put behavior where it belongs — prefer `InvoiceNumberService::generate()`
over `Helper::formatInvoiceNumber()`.

## Don't Repeat Business Logic

If the same rule (e.g. `calculateTax()`) is needed in multiple places, give it one owner
and reuse it. Never copy/paste business logic across controllers.

## Dependency Injection

Inject dependencies via the constructor; let Laravel's container resolve them. Avoid
manually newing services inside business logic (`new SomeService()`).

```php
public function __construct(
    private BackupStorageService $storage,
    private BackupRepository $repository,
) {}
```
## External Services

Isolate external APIs and infrastructure behind dedicated services.

Prefer names that identify the responsibility or integration, for example:

- `FortisPaymentService`
- `S3StorageService`

Do not scatter raw HTTP requests or infrastructure-specific calls through
controllers or unrelated business classes.

## Database Access & Transactions

Keep data access readable: prefer Eloquent, Query Builder, or a dedicated query class for
genuinely complex queries over large raw SQL. Always consider N+1 queries, indexes,
locking, and performance. Transactions must be explicit where atomicity is required. Do not introduce a
global `BaseAction` transaction wrapper that automatically wraps every Action.
(e.g. create invoice + items + payment + ledger update) in an explicit transaction.

## Security & Correctness First

Readability matters, but never simplify in a way that weakens authentication,
authorization, tenant isolation, validation, data integrity, encryption, or auditability.

## Before Writing New Code

1. Understand the requirement.
2. Inspect existing code; check whether similar functionality already exists (avoid duplicates).
3. Identify the correct module.
4. Identify the correct Action (or a shared Service, only for a genuine shared responsibility).
5. Check tenant implications.
6. Implement the smallest clean solution.
7. Add appropriate tests and meaningful logging where required.
8. Run the relevant tests.

## Golden Rule

Prefer simple, readable, explicit, small, testable, and maintainable code over clever,
complex, highly abstract, compressed, or over-engineered code. If a developer can read the
main method and understand the whole workflow without opening every function, the code is
probably structured well.
