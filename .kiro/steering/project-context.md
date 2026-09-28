---
inclusion: always
---

# Project Context

This repository is building a modular ERP SaaS platform.
The long-term goal is to support many companies using one application codebase.
Architecture:

    Platform
        ↓
    Platform Database
        ↓
    Tenant / Workspace Resolver
        ↓
    Separate Workspace Database per Company
        ↓
    Independent ERP Modules under Modules/{ModuleName}

Shared/core functionality remains under app/.

The system must support:

- Multi-company SaaS
- Separate database per company
- Modular business features
- Module installation
- Module enable/disable
- Module update
- Module uninstall
- REST APIs
- Inertia.js
- React
- Event-driven architecture
- Queues
- Notifications
- Audit logging
- Permissions
- SaaS subscriptions
- Future marketplace

Initial business modules are NOT the current priority.

Phase 0 focuses on foundation only (Laravel, MySQL/Redis/queue/scheduler, Inertia React TypeScript, health checks, conventions). Do not implement auth, RBAC, tenant provisioning, or business modules until the foundation phase is complete.

First priority:

1. Platform Core
2. Tenant Management
3. Database Provisioning
4. Authentication
5. Module System
6. Events
7. Queues
8. API foundation
9. Permissions
10. Audit
11. Testing
12. Documentation

Do not introduce business modules until the foundation is stable.

# Golden Rules

CORE IS STABLE.
MODULES ARE REPLACEABLE.
TENANTS ARE ISOLATED.
EVENTS REDUCE COUPLING.
BUSINESS LOGIC DOES NOT BELONG IN CONTROLLERS.
SECURITY CANNOT DEPEND ON THE FRONTEND.
NEVER ALLOW CROSS-TENANT DATA ACCESS.