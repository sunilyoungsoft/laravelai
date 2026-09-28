---
inclusion: always
---

# Testing Rules

## General

* Every new feature must include automated tests.
* Prefer feature tests for application workflows.
* Use unit tests for isolated business logic.
* Do not consider a feature complete until its tests pass.

## Tenant Isolation

* Every tenant-specific feature must have tenant isolation tests.
* Verify that Tenant A cannot access Tenant B data.
* Verify that Tenant A cannot modify or delete Tenant B data.
* Verify that APIs reject cross-tenant access.
* Verify that queued jobs execute against the correct tenant database.
* Verify that events and listeners preserve tenant context.
* Verify that missing tenant context fails safely.

## Module System

Test:

* Module installation
* Module enable
* Module disable
* Module update
* Module uninstall
* Module dependency validation
* Missing dependency handling
* Circular dependency prevention
* Migration execution
* Migration rollback
* Permission registration
* Route registration
* Event registration

## Database

Test:

* Master database operations
* Tenant database provisioning
* Dynamic database connection switching
* Tenant database migrations
* Database connection restoration
* Transaction rollback
* Failed migration handling

## Authentication & Authorization

Test:

* Login
* Logout
* Session handling
* Company selection
* Tenant resolution
* Role permissions
* Permission denial
* Cross-tenant authorization
* API authentication

## Events

Test:

* Event dispatch
* Event listeners
* Event ordering where required
* Tenant context inside listeners
* Failed listeners
* Queued listeners

## Queues

Test:

* Tenant context is preserved
* Correct tenant database is selected
* Failed jobs
* Retry behavior
* Job idempotency where required

## API

Test:

* Authentication
* Authorization
* Validation
* Successful responses
* Error responses
* Pagination
* Filtering
* Sorting
* API versioning
* Rate limiting where applicable
* Cross-tenant access prevention

## Security

Every security-sensitive feature must include negative tests.

Examples:

* User from Company A attempts to read Company B data.
* User from Company A attempts to update Company B data.
* User without permission attempts a restricted action.
* Request without tenant context attempts tenant access.
* Invalid tenant identifier is supplied.
* Disabled module API is accessed.

These tests must verify that access is denied safely.

## Regression

Before merging changes:

1. Run the relevant module tests.
2. Run tenant isolation tests.
3. Run authentication and authorization tests.
4. Run the full test suite.
5. Run static analysis.
6. Run code formatting checks.

Never disable an existing test just to make a new feature pass.

If a test must change because the expected behavior changed, update the test intentionally and document why.

## Test Structure

Organize tests consistently:

```text
tests/
    Unit/
    Feature/
    Integration/
    Security/
```

Module-specific tests should remain close to the module when appropriate.

## Testing Principle

The most important testing rule is:

**Tenant isolation must never be assumed; it must be continuously tested.**
