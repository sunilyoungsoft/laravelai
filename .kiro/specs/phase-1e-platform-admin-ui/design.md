# Phase 1E — Platform Admin UI — Design

> Design for the requirements in `requirements.md`. UI-only phase: it adds Inertia pages, React
> components, thin controllers, a policy, Platform auth, and frontend tooling. It reuses the
> Phase 1D backend unchanged. All server work follows Action-first, explicit `platform`
> connection, and server-authoritative authorization.

## 1. Architecture fit

```
React page (Inertia) → thin Platform controller
    → CompanyPolicy authorization (before business work)
    → existing Service / Action (CompanyService, ProvisionWorkspaceAction, Domain Actions)
    → Inertia response / redirect + flash
```

The admin UI is served from **central** (Platform) routes. It never runs under the
`tenant.resolve` middleware and never initializes Stancl tenancy — it operates on the `platform`
connection only. Provisioning triggers a workspace build via the existing Action, which itself
owns the (Stancl-mediated) tenant work; the controller does not touch tenant connections.

## 2. Authentication (1E-A)

- Add a thin `Auth\PlatformLoginController` (or an invokable pair) with `showLogin`, `login`,
  `logout`. `login` validates `email`+`password`, calls `Auth::guard('platform')->attempt()`,
  regenerates the session on success, and redirects to the Dashboard; `logout` logs out of the
  `platform` guard, invalidates + regenerates the session token, redirects to login.
- A `LoginRequest` (Form Request) owns HTTP validation and a generic "credentials do not match"
  error (no field-level disclosure). Optional throttling via Laravel's rate limiter.
- No registration/reset/2FA routes. `config/auth.php` already defines the `platform` guard and
  `platform_users` provider — no auth config changes needed.

## 3. Authorization (1E-B)

- `App\Policies\CompanyPolicy` with `viewAny`, `view`, `create`, `provision`, and
  `manageDomains` (name TBD) abilities. Each delegates to a single private predicate:
  "the acting `PlatformUser` holds the **active** Admin system role" — matching the existing
  `slug=admin` + `is_system=true` check used by `CreatePlatformAdminService`.
- Add a small `PlatformUser::isPlatformAdmin(): bool` helper (reads the already-defined `roles()`
  relation) so the policy stays readable. (This is an additive model helper, not a backend change
  to Phase 1D behavior.)
- Register the policy (via `Gate`/`AuthServiceProvider` or Laravel 11+ auto-discovery under
  `app/Policies`). Controllers call `$this->authorize(...)` before any work.
- **Shared Inertia props:** extend `HandleInertiaRequests::share()` with:
  ```php
  'auth' => [
      'user' => fn () => $request->user()?->only('id', 'name', 'email'),
      'can'  => fn () => [
          'companies.viewAny'  => $request->user()?->can('viewAny', Company::class) ?? false,
          'companies.create'   => $request->user()?->can('create', Company::class) ?? false,
          // per-company abilities resolved on the Details page props, not globally
      ],
  ],
  ```
  Per-company abilities (`view`, `provision`, `manageDomains`) are passed as booleans in the
  specific page's props (computed from the policy) so the UI can disable the exact buttons.

## 4. Controllers & routes

New thin controllers under `app/Http/Controllers/Platform/`:

| Controller | Responsibility | Delegates to |
|------------|----------------|--------------|
| `Auth\PlatformLoginController` | login form / attempt / logout | `platform` guard |
| `DashboardController` | status counts | Company query (platform) |
| `CompanyController` | `index`, `create`, `store`, `show` | `CompanyService::create` |
| `WorkspaceProvisionController` | `store` (provision/retry, **sync**) | `ProvisionWorkspaceAction` |
| `CompanyDomainController` | `store` (add), `update` (change effective) | `CreateDomainAction` / `SetPrimaryDomainAction` |

- New route file `routes/platform.php`, registered in `bootstrap/app.php` for web middleware.
- Guest routes: `GET /login`, `POST /login`.
- Auth group (`auth:platform`): `POST /logout`, `GET /dashboard`, `GET /companies`,
  `GET /companies/create`, `POST /companies`, `GET /companies/{company}`,
  `POST /companies/{company}/provision`, `POST /companies/{company}/domain`,
  `PUT /companies/{company}/domain`.
- Route-model binding resolves `{company}` on the `platform` connection (default scope excludes
  soft-deleted). All controller actions authorize via `CompanyPolicy` first.

### Provisioning endpoint (1E-E)

`WorkspaceProvisionController::store` authorizes `provision`, calls
`ProvisionWorkspaceAction::execute($company, $request->user())` **synchronously**, catches
`CompanyNotProvisionableException` / `WorkspaceProvisioningException`, and redirects back with a
success or error flash. No job dispatch. The Workspace card models status as a lifecycle so a
future async swap (dispatch a job instead) needs no UI change.

### Domain endpoints (1E-C)

- `store` (add, only when the company has no effective domain): builds `CreateDomainData` from a
  `DomainRequest` (type subdomain|custom, hostname/label, `isPrimary=true`), calls
  `CreateDomainAction` (which can make it primary on create).
- `update` (change the effective domain): creates the replacement via `CreateDomainAction` and
  promotes it with `SetPrimaryDomainAction`, so the single-primary invariant stays server-side.
  No set-primary UI, no list — the controller resolves "effective" = the company's `is_primary`
  domain.

## 5. Frontend tooling (1E-D, Stage 0)

- **shadcn/ui** initialized for **Tailwind v4** (CSS-first; the project uses `@tailwindcss/vite`
  with no `tailwind.config.js`). Expect `components.json`, CSS variables/tokens added to
  `resources/css/app.css`, and generated primitives under `resources/js/components/ui/`.
  Use the shadcn CLI version that supports Tailwind v4 + React 19. Verify generated output builds
  before proceeding.
- **Path aliases** already correct: `@/* → resources/js/*` in both `tsconfig.json` and
  `vite.config.js`. shadcn's `components.json` must point at the same alias.
- **ESLint + Prettier**: add dev deps and configs (TypeScript + React + React-Hooks rules,
  Prettier for formatting). Add `package.json` scripts: `lint`, `lint:fix`, `format`,
  `type-check` (`tsc --noEmit`). Pin `typescript` to `^6.x` (not `^7.0.2`) and keep
  `@types/node@^26.6.1` unchanged — TS 7.0 has no stable programmatic API and
  typescript-eslint@8.x rejects it; revisit when TS 7.1 + typescript-eslint support lands.
- **Only add** shadcn + lint dependencies. No upgrades to React/Vite/Tailwind/Inertia.

## 6. Reusable components (Stage 4)

Under `resources/js/components/` (shared) with shadcn primitives under `components/ui/`:

| Component | Role |
|-----------|------|
| `PlatformLayout` | Authenticated shell: sidebar + header + flash/toast region |
| `PageHeader` | Title + optional actions slot |
| `CompanyStatusBadge` | Maps `CompanyStatus` → labeled, colored badge |
| `CompanyTable` | Companies list rows (name, slug, status, domain) + pagination |
| `CompanyInformationCard` | Company profile/metadata display |
| `WorkspaceStatusCard` | Lifecycle state + provision/retry action (per F2/F3) |
| `DomainCard` | Single effective domain + add/change (per F4/F5) |
| `EmptyState` | Shared empty-state (icon, message, optional action) |
| `ConfirmDialog` | Confirm irreversible/mutating actions |
| `LoadingButton` | Button with pending/disabled/spinner state |

Typed props throughout (no `any`). Shared TS types for `Company`, `CompanyStatus`, `Domain`,
`auth` live in `resources/js/types/`.

## 7. Inertia pages (Stage 5)

Under `resources/js/Pages/Platform/` resolved by the existing `./Pages/{name}.tsx` glob:

| Page | Route | Notes |
|------|-------|-------|
| `Auth/Login` | `GET /login` | Standalone (no PlatformLayout) |
| `Dashboard` | `GET /dashboard` | Status-count cards; empty state |
| `Companies/Index` | `GET /companies` | `CompanyTable`, search + status filter from query |
| `Companies/Create` | `GET /companies/create` | Profile-field form → `store` |
| `Companies/Show` | `GET /companies/{company}` | Info + Workspace + Domain cards; per-company `can` props |

## 8. Shared types & name-neutrality

- The app name comes from the existing shared `appName` prop; no product name is hardcoded in
  components (product name is undecided).
- `CompanyStatus` labels/colors are defined once (a single `statusMeta` map) and reused by the
  badge and dashboard.

## 9. Testing approach (Stage 6)

- **PHP (PHPUnit):** feature tests for login success/failure, logout, auth redirect; policy
  tests (admin allowed, non-admin/guest forbidden) for each ability; controller tests that
  Create makes a `pending` company with no provisioning, that provision/retry delegates to the
  Action and reflects status, and that add/change domain delegates to the Domain Actions. Negative
  authorization tests return 403/redirect. Reuse existing platform testing DB conventions.
- **Frontend:** `type-check`, `lint`, and `build` must pass. (No component test runner is being
  introduced in this phase unless requested.)
- Run with the PHP 8.4 binary; verify after each stage.

## 10. Risks / watch-list

- shadcn/ui + Tailwind v4 (CSS-first) + React 19 tooling is new here — the init may need the
  canary/latest CLI. Validate a trivial generated component builds before building pages.
- Keep the admin UI strictly on central routes; never let it pick up `tenant.resolve`.
- `auth.can` is UX only; every controller must still authorize. Prove with negative tests.
- Do not drift into multi-domain UI — "effective domain" is deliberately singular (1E-C).
