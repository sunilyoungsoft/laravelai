---
inclusion: always
---

# Security Rules

Security and tenant isolation are critical.

## Authorization

Never rely only on frontend checks.

Every protected operation must be authorized server-side.

Use:

- Policies
- Gates
- Permissions
- Roles

## Tenant Isolation

Never trust:

- company_id from request body
- tenant_id from query parameters
- tenant_id from URL

unless it is validated against the authenticated user's allowed tenants.

Prefer TenantContext.

## Input

Validate all external input.

Never trust:

- request parameters
- uploaded files
- headers
- webhook payloads
- API input

## Mass Assignment

Use explicit fillable/guarded strategies.

Do not blindly accept request data into models.

## Secrets

Never commit:

- passwords
- API keys
- tokens
- private keys
- database credentials

Use environment variables or secure secret storage.

## Logs

Never log:

- passwords
- authentication tokens
- payment secrets
- sensitive personal data unnecessarily

## Files

Tenant files must be isolated.

A user from Tenant A must never be able to access Tenant B files by manipulating a path or identifier.