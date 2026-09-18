# Laravel AI ERP

Modular monolith ERP SaaS platform (Phase 0 foundation).

## Stack

- Laravel 13 / PHP 8.4
- MySQL 8.x (Platform DB + Workspace DB connection stub)
- Redis (cache + queue)
- Laravel Queue + Scheduler readiness
- Inertia.js + React + TypeScript + Tailwind CSS

## Phase 0 scope

Included:

- Application skeleton and local configuration
- Platform MySQL connection + unused `workspace` connection stub
- Redis cache/queue with Predis
- Inertia React TypeScript welcome page
- Health endpoints: `/up`, `/api/health`
- `Modules/` placeholder for future business modules
- Cursor project rules

Not included (intentionally):

- Authentication, users, roles, permissions
- Tenant/workspace provisioning or middleware
- Companies, billing, subscriptions
- Business modules

## Local setup

1. Use PHP **8.4** (Laragon: `C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64`).
2. Ensure MySQL 8.x and Redis are running.
3. Create the Platform database: `laravelai_platform`.
4. Copy environment file and install dependencies:

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
npm run build
php artisan migrate
```

5. Set `DB_*` and optional `WORKSPACE_DB_*` values in `.env`.

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
- Default DB connection is the Platform database.
- Application timestamps use UTC via Laravel; do not use MySQL `NOW()` / `CURRENT_TIMESTAMP` for application fields.
- Primary keys will use ULID; SQL naming uses snake_case plural tables.
