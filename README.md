# Laravel AI ERP

Modular monolith ERP SaaS platform.

## Stack

- Laravel 13 / PHP 8.4
- MySQL 8.x (semantic `platform` connection + `workspace` stub)
- Redis (cache + queue)
- Laravel Queue + Scheduler readiness
- Inertia.js + React + TypeScript + Tailwind CSS

## Phase status

### Phase 0 — Foundation

- Application skeleton, Redis queue/cache, Inertia React TypeScript welcome page
- Health endpoints: `/up`, `/api/health`
- `Modules/` placeholder

### Phase 1B — Platform RBAC foundation

- Semantic DB connection: `platform` (`PLATFORM_DB_*`)
- Tables: `platform_users`, `platform_roles`, `platform_permissions`, pivots
- Models: `PlatformUser`, `PlatformRole`, `PlatformPermission`
- Auth provider `platform_users` + guard `platform` (temporary default until Workspace auth)
- Soft-delete-safe Admin role seeder (`slug=admin`, `is_system=true`)
- CLI: `php artisan platform:create-admin`
- Schema docs: [`docs/database/platform.dbml`](docs/database/platform.dbml)

**Admin permission invariant:** The Admin system role does not use wildcard permissions. When future Platform features add permission records, those permissions must be assigned to the Admin system role.

Not included yet: login UI, 2FA flows, Companies, Workspace users/RBAC, Filament, Spatie Permission.

## Local setup

1. Use PHP **8.4**.
2. Ensure MySQL 8.x and Redis are running.
3. Create databases:
   - Platform: value of `PLATFORM_DB_DATABASE` (example: `laravelai_platform`)
   - Testing: `laravelai_platform_testing`
4. Copy env and install:

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
npm run build
php artisan migrate --database=platform
php artisan db:seed --class=PlatformRoleSeeder --database=platform
php artisan platform:create-admin
```

5. For tests, ensure `.env.testing` (or `phpunit.xml`) uses `PLATFORM_DB_DATABASE=laravelai_platform_testing` with valid MySQL credentials. Tests refuse to run against the live platform database.

## Development commands

```bash
composer run dev
php artisan queue:work
php artisan schedule:work
php artisan test
npm run build
```

## Architecture notes

- Shared/core code lives under `app/`.
- Future business modules live under `Modules/{ModuleName}/`.
- Platform models/migrations/seeders/services use connection `platform` explicitly.
- Future Platform auth must use `Auth::guard('platform')`.
- Application timestamps use UTC via Laravel; do not use MySQL `NOW()` / `CURRENT_TIMESTAMP`.
- Primary keys use ULID; SQL naming uses snake_case plural tables.
- Foreign keys use `NO ACTION` (`noActionOnDelete()`).
- Keep `docs/database/platform.dbml` updated whenever platform schema changes.
