# Phase 1E — Platform Admin UI — Implementation Tasks

> **Status: DRAFT — awaiting approval. Do NOT implement until approved.** Tasks follow the
> user-confirmed build order (Stage 0 → 6) and the decisions 1E-A…1E-E. Each task is small and
> testable. **Verify after each stage.** Run artisan/pint/npm with the PHP 8.4 binary
> (`C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64\php.exe`). No change to the Phase 1D
> tenancy/domain/provisioning backend.

## Stage 0 — Frontend tooling (1E-D)

- [x] 0.1 Add ESLint + Prettier dev deps and configs (TypeScript + React + React-Hooks rules;
  Prettier). Add scripts `lint`, `lint:fix`, `format`, `type-check` (`tsc --noEmit`). Pin
  `typescript` to `^6.x` (not `^7.0.2`) and keep `@types/node@^26.6.1` unchanged. _Req: 1E-D._
  - **Decision:** typescript pinned to `^6.x` instead of `^7.0.2` — TS 7.0 has no stable
    programmatic API and typescript-eslint@8.x rejects it; revisit when TS 7.1 + typescript-eslint
    support lands. (Dropping `baseUrl` from `tsconfig.json` and typing the Inertia `resolve`
    page module were required by the stricter TS 6 toolchain.)
- [x] 0.2 Initialize shadcn/ui for Tailwind v4 (CSS-first): `components.json` pointing at the
  `@/*` alias, tokens/CSS vars into `resources/css/app.css`, primitives under
  `resources/js/components/ui/`. Add one trivial primitive (e.g. Button) as a smoke test. _Req: 1E-D._
- [x] 0.3 **Verify:** `npm run lint`, `npm run type-check`, `npm run build` all pass. _Req: 7._

## Stage 1 — Platform auth (1E-A)

- [x] 1.1 `LoginRequest` (email+password; generic credential error, no field disclosure). _Req: A2, A3._
- [x] 1.2 `Auth\PlatformLoginController` (`showLogin`, `login` via `Auth::guard('platform')`,
  session regenerate; `logout` invalidate+regenerate). _Req: A2, A4._
- [x] 1.3 Guest routes `GET/POST /login` + `POST /logout` in a new `routes/platform.php`;
  register the file in `bootstrap/app.php`. Auth-protect later groups with `auth:platform`. _Req: A1._
- [x] 1.4 Tests: login success → dashboard; invalid creds → generic error; logout ends session;
  unauthenticated access to a protected route redirects to login. _Req: A1–A5, 7._
- [x] 1.5 **Verify:** `php artisan test` (new auth tests + full suite green), `pint --test`. _Req: 7._

## Stage 2 — Authorization (1E-B)

- [x] 2.1 Add `PlatformUser::isPlatformAdmin()` (reads existing `roles()`; active Admin system
  role `slug=admin`, `is_system=true`). Additive helper only. _Req: B1._
- [x] 2.2 `App\Policies\CompanyPolicy` (`viewAny`, `view`, `create`, `provision`,
  `manageDomains`) all gated on `isPlatformAdmin()`; register the policy. _Req: B1, B2._
- [x] 2.3 Extend `HandleInertiaRequests::share()` with `auth.user` + global `auth.can`
  (`companies.viewAny`, `companies.create`). _Req: B3._
- [x] 2.4 Tests: each ability allows an admin and forbids a non-admin and a guest. _Req: B1–B3, 7._
- [x] 2.5 **Verify:** `php artisan test`, `pint --test`. _Req: 7._

## Stage 3 — Thin controllers + routes

- [x] 3.1 `DashboardController` — Company status counts on `platform`, excluding soft-deleted. _Req: C1, C2._
- [x] 3.2 `CompanyController@index` — search + status filter from query (reflected in URL),
  soft-deleted excluded, paginated. _Req: D1–D4._
- [x] 3.3 `CompanyController@create` + `@store` — form page + `CompanyService::create` (pending,
  no provisioning); redirect to Show with success flash; validation errors map to fields. _Req: E1–E4._
- [x] 3.4 `CompanyController@show` — Company + effective (primary) Domain + per-company `can`
  props (`view`, `provision`, `manageDomains`). _Req: F1, B3._
- [x] 3.5 `WorkspaceProvisionController@store` — authorize `provision`; call
  `ProvisionWorkspaceAction::execute()` **synchronously**; catch the two provisioning exceptions;
  success/error flash. _Req: F2, F3, 1E-E._
- [x] 3.6 `CompanyDomainController@store`/`@update` — add when none (via `CreateDomainAction`),
  change via `CreateDomainAction` + `SetPrimaryDomainAction`; surface validation errors. _Req: F4, F5, 1E-C._
- [x] 3.7 Register all routes in `routes/platform.php` under `auth:platform` with policy
  authorization; `{company}` bound on `platform`. _Req: B2._
- [x] 3.8 Tests: counts; list search/filter + soft-delete exclusion; create = pending + no
  provisioning; provision/retry delegates and reflects status; add/change domain delegates;
  every action 403s for non-admin. _Req: C–F, 7._
- [x] 3.9 **Verify:** `php artisan test`, `pint --test`. _Req: 7._

## Stage 4 — Reusable components

- [x] 4.1 Shared TS types (`Company`, `CompanyStatus`, `Domain`, `auth`) + a single
  `statusMeta` map (labels/colors). _Req: 8._
- [x] 4.2 Build: `PlatformLayout`, `PageHeader`, `CompanyStatusBadge`, `CompanyTable`,
  `CompanyInformationCard`, `WorkspaceStatusCard`, `DomainCard`, `EmptyState`, `ConfirmDialog`,
  `LoadingButton` — typed props, no `any`, loading/empty/pending states where relevant. _Req: 6, G1, G2._
- [x] 4.3 **Verify:** `npm run lint`, `npm run type-check`, `npm run build`. _Req: 7._

## Stage 5 — Inertia pages

- [x] 5.1 `Platform/Auth/Login` (standalone, no layout). _Req: A2._
- [x] 5.2 `Platform/Dashboard` (status-count cards + empty state). _Req: C1–C3._
- [x] 5.3 `Platform/Companies/Index` (CompanyTable, search + status filter from query,
  loading/empty). _Req: D1–D4._
- [x] 5.4 `Platform/Companies/Create` (profile form, field errors, success redirect). _Req: E1–E4._
- [x] 5.5 `Platform/Companies/Show` (Information + Workspace + Domain cards; actions gated by
  per-company `can`; provision/retry + add/change domain with full UX states). _Req: F1–F5, G1, G2._
- [x] 5.6 **Verify:** `npm run lint`, `npm run type-check`, `npm run build`; click-through of the
  five pages against a seeded admin. _Req: 7._

## Stage 6 — Full verification & docs

- [x] 6.1 Run the whole PHP suite (PHP 8.4) + `pint --test`; ensure no existing test weakened and
  all Phase 1E tests pass. _Req: 7._
- [x] 6.2 Run `npm run lint`, `npm run type-check`, `npm run build` clean. _Req: 7._
- [x] 6.3 Update `project-status.md` (and `index.md` if needed) to record Phase 1E delivery and
  the confirmed 1E-A…1E-E decisions; note sync-only provisioning and the single-effective-domain
  UI constraint. _Req: process rules._

## Dependency order

0 → 1 → 2 → 3 (server complete + tested) → 4 → 5 (UI on a proven backend) → 6. Stage 4 may begin
once Stage 0 is green, but pages (5) need controllers/props (3) and components (4). Verify after
every stage before moving on.
