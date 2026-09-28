---
inclusion: always
---

# Project Coding Style

This project follows a simple, readable, maintainable coding style.

The primary goal is:

> Code should be easy for a human developer to read, understand, debug, test, and maintain.

Follow:

**KISS — Keep It Simple, Stupid.**

Prefer simple and explicit code over clever or overly abstract code.

---

# 1. Service-First Business Logic

Whenever implementing a business/application operation, create or use a Service class.

Examples:

* BackupService
* InvoiceService
* PurchaseService
* PaymentService
* EmployeeService
* NotificationService
* TenantService

Controllers, Commands, Jobs, and Listeners should delegate meaningful work to Services.

Prefer:

```php
Controller
    ↓
Service
    ↓
Repository / Model
```

or:

```php
Command
    ↓
Service
    ↓
Repository / Model
```

Do not place complex business logic directly inside:

* Controllers
* Commands
* Jobs
* Listeners
* Routes

---

# 2. Small Functions

Avoid large functions.

If a function is doing multiple things, divide it into small functions with clear responsibilities.

Bad:

```php
public function backup()
{
    // find database
    // create backup
    // upload backup
    // save URL
    // send notification
    // logging
    // error handling
}
```

Prefer:

```php
public function backup(): string
{
    $database = $this->selectDatabase();
    $backupFile = $this->createBackup($database);
    $backupUrl = $this->uploadBackup($backupFile);

    return $this->storeBackupUrl($backupUrl);
}
```

The main function should tell the story of the operation.

A developer should be able to understand the workflow without reading every implementation detail.

---

# 3. Main Function as an Orchestrator

When an operation contains multiple steps, the main method should orchestrate those steps.

Example:

```php
public function execute(): BackupResult
{
    $database = $this->selectDatabase();
    $backupFile = $this->takeBackup($database);
    $backupUrl = $this->storeBackup($backupFile);

    return $this->saveBackupRecord($backupUrl);
}
```

Each method should have one clear responsibility.

Prefer:

```text
execute()
    ↓
selectDatabase()
    ↓
takeBackup()
    ↓
storeBackup()
    ↓
saveBackupRecord()
```

over one large function.

---

# 4. Single Responsibility

A function should have one clear reason to change.

Bad:

```php
createInvoice()
```

which:

* validates customer
* calculates tax
* calculates discount
* creates invoice
* sends email
* generates PDF
* records audit log
* updates accounting

Prefer:

```text
createInvoice()
    ↓
validateInvoice()
    ↓
calculateTotals()
    ↓
saveInvoice()
    ↓
dispatchInvoiceCreated()
```

Listeners/jobs can handle asynchronous operations such as:

```text
InvoiceCreated
    ↓
Generate PDF
Send Email
Create Audit Log
```

---

# 5. Readability Over Cleverness

Do not optimize for fewer lines of code.

Optimize for understanding.

Avoid unnecessarily clever:

```php
return collect($items)->filter(...)->map(...)->reduce(...);
```

when a simple loop would be easier to understand.

Do not use complicated one-liners when several simple lines are clearer.

Prefer explicit code.

---

# 6. Naming

Names must explain intent.

Prefer:

```php
selectTenantDatabase()
createBackupFile()
uploadBackupFile()
saveBackupRecord()
```

Avoid:

```php
process()
handle()
doIt()
run()
executeStuff()
helper()
```

Generic names are acceptable only when their context makes the responsibility obvious.

---

# 7. Logging

Important application operations must have useful logs.

Log important lifecycle points:

```text
Started
Completed
Failed
```

Example:

```php
Log::info('Database backup started', [
    'tenant_id' => $tenantId,
]);
```

On success:

```php
Log::info('Database backup completed', [
    'tenant_id' => $tenantId,
    'backup_url' => $backupUrl,
]);
```

On failure:

```php
Log::error('Database backup failed', [
    'tenant_id' => $tenantId,
    'exception' => $exception->getMessage(),
]);
```

Do not log sensitive information.

Never log:

* passwords
* API secrets
* access tokens
* database passwords
* private keys
* sensitive personal information unnecessarily

---

# 8. Error Handling

Errors must be handled at the appropriate boundary.

Do not silently ignore exceptions.

Bad:

```php
try {
    ...
} catch (\Exception $e) {
}
```

Prefer:

```php
try {
    ...
} catch (\Throwable $exception) {
    Log::error('Database backup failed', [
        'tenant_id' => $tenantId,
        'error' => $exception->getMessage(),
    ]);

    throw $exception;
}
```

Only catch exceptions when there is something meaningful to do.

Do not catch an exception just to hide it.

---

# 9. Logging + Error Handling

For important operations, use this pattern:

```text
Start
  ↓
Execute
  ↓
Success → Log success
  ↓
Failure → Log error → Handle/throw
```

Do not add excessive logging to every small method.

Log meaningful business/application boundaries.

---

# 10. Example: Database Backup

Preferred architecture:

```php
class DatabaseBackupService
{
    public function backup(): BackupResult
    {
        $database = $this->selectDatabase();
        $backupFile = $this->takeBackup($database);
        $backupUrl = $this->uploadBackup($backupFile);

        return $this->saveBackupRecord($backupUrl);
    }
}
```

Methods:

```text
selectDatabase()
takeBackup()
uploadBackup()
saveBackupRecord()
```

The cron command should remain very small:

```php
public function handle(DatabaseBackupService $backupService): int
{
    $backupService->backup();

    return self::SUCCESS;
}
```

The cron command schedules the operation.

The Service performs the operation.

---

# 11. Avoid Giant Services

"Always use a Service" does NOT mean:

```text
MegaService.php
    5000 lines
```

Services must also remain small and focused.

Prefer:

```text
TenantService
TenantProvisioningService
TenantDatabaseService

InvoiceService
InvoiceCalculationService
InvoicePaymentService

BackupService
BackupStorageService
BackupCleanupService
```

Split a Service when its responsibilities become unrelated or difficult to understand.

---

# 12. Don't Create Abstractions Without a Reason

Do not automatically create:

```text
Interface
Repository
Factory
Strategy
Manager
Helper
Utility
Adapter
```

for every class.

Create abstractions when they solve a real problem such as:

* multiple implementations
* external integrations
* testing boundaries
* module boundaries
* infrastructure isolation

Simple code is preferred.

---

# 13. Avoid Generic Helper Classes

Avoid:

```text
Helper.php
CommonHelper.php
Utility.php
GlobalFunctions.php
```

Instead, put functionality where it belongs.

Example:

Bad:

```php
Helper::formatInvoiceNumber();
```

Prefer:

```php
InvoiceNumberService::generate();
```

or an appropriate domain/application component.

---

# 14. Don't Repeat Business Logic

If the same business rule is used in multiple places, identify the correct owner and reuse it.

Do not copy/paste:

```php
calculateTax()
```

into:

* InvoiceController
* SalesController
* PurchaseController

Create the appropriate shared business component.

---

# 15. Comments

Code should explain WHAT.

Comments should explain WHY.

Avoid:

```php
// Get database
$database = $this->selectDatabase();
```

Prefer comments only for non-obvious decisions:

```php
// Backup must run against the tenant database,
// never the platform database.
```

Do not use comments to compensate for unclear code.

If the code is difficult to understand, improve the code first.

---

# 16. Avoid Deep Nesting

Avoid:

```php
if (...) {
    if (...) {
        if (...) {
            if (...) {
                ...
            }
        }
    }
}
```

Prefer:

* guard clauses
* early returns
* small methods

Example:

```php
if (!$tenant) {
    throw new TenantNotFoundException();
}

if (!$tenant->isActive()) {
    throw new TenantInactiveException();
}

return $this->process($tenant);
```

---

# 17. Method Length

There is no arbitrary hard limit on lines.

However, if a method requires significant scrolling or contains multiple unrelated responsibilities, consider splitting it.

The goal is:

> A developer should understand the method's purpose quickly.

---

# 18. Class Length

Do not allow classes to become dumping grounds.

If a class starts handling:

* database operations
* API calls
* notifications
* calculations
* file management
* business rules

split responsibilities into appropriate classes.

---

# 19. Dependency Injection

Prefer dependency injection.

Example:

```php
public function __construct(
    private BackupStorageService $storage,
    private BackupRepository $repository,
) {
}
```

Avoid creating dependencies manually throughout business logic:

```php
$service = new SomeService();
```

Use Laravel's container where appropriate.

---

# 20. External Services

External APIs must be isolated behind dedicated services.

Example:

```text
PaymentService
    ↓
FortisService

WhatsAppService
    ↓
WhatsApp API

StorageService
    ↓
S3
```

Do not scatter HTTP requests throughout controllers or business classes.

---

# 21. Database Access

Keep database access readable.

Avoid massive raw SQL queries unless they provide a real benefit.

Prefer:

* Eloquent
* Query Builder
* dedicated query classes for genuinely complex queries

Always consider:

* N+1 queries
* indexes
* transactions
* locking
* query performance

---

# 22. Transactions

If an operation modifies multiple records that must succeed together, use a transaction.

Example:

```text
Create Invoice
    ↓
Create Items
    ↓
Create Payment
    ↓
Update Ledger
```

These operations should be atomic when required by the business rule.

---

# 23. Security Must Override Style

Readable code is important, but security and correctness come first.

Never simplify code in a way that compromises:

* authentication
* authorization
* tenant isolation
* validation
* data integrity
* encryption
* auditability

---

# 24. Before Writing New Code

Before implementing a feature:

1. Understand the requirement.
2. Inspect existing code.
3. Check whether similar functionality already exists.
4. Identify the correct module.
5. Identify the correct Service.
6. Check tenant implications.
7. Implement the smallest clean solution.
8. Add appropriate tests.
9. Add meaningful logging where required.
10. Run relevant tests.

Do not create duplicate functionality.

---

# 25. Final Principle

Always prefer:

```text
Simple
Readable
Explicit
Small
Testable
Maintainable
```

over:

```text
Clever
Complex
Highly abstract
Compressed
Over-engineered
```

The code should be understandable by another developer who did not write it.

## Golden Rule

If a developer can read the main method and understand the complete workflow without opening every function, the code is probably structured well.
