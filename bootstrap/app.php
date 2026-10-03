<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InitializeTenancyByResolvedDomain;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Platform admin routes (central, `platform` guard) run with the web middleware
            // group so they get sessions, CSRF, and the shared Inertia props. They are
            // host-scoped to the central platform domains so the admin UI (and its /login)
            // never answers on a tenant/company host — tenant subdomains are served by the
            // workspace routes instead (1F-D). Registering once per central domain keeps the
            // scoping config-driven via tenancy.central_domains.
            foreach ((array) config('tenancy.central_domains', []) as $centralDomain) {
                Route::domain($centralDomain)
                    ->middleware('web')
                    ->group(__DIR__.'/../routes/platform.php');
            }

            // Workspace (tenant) routes run only on a resolved Company host: tenant.resolve
            // initializes tenancy from the hostname before any workspace auth. They are NOT
            // host-scoped to a fixed list — any non-central host that resolves to an active
            // Company is served here.
            Route::middleware('web')
                ->group(__DIR__.'/../routes/workspace.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Resolve hostname -> Company (Platform data) and initialize Stancl tenancy.
        $middleware->alias([
            'tenant.resolve' => InitializeTenancyByResolvedDomain::class,
        ]);

        // Tenancy MUST initialize before authentication resolves a user. The `workspace`
        // guard loads WorkspaceUser on the default connection, which is only the tenant
        // database once tenancy is initialized; without this ordering the auth middleware
        // can run first and query workspace_users on the central `platform` connection
        // (table-not-found). Insert the resolver into Laravel's existing priority list
        // immediately before the auth middleware. The list keys auth by the
        // AuthenticatesRequests contract (which Authenticate implements), not the concrete
        // class — so target the contract. StartSession still precedes both.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: InitializeTenancyByResolvedDomain::class,
        );

        // Unauthenticated requests redirect to the correct login for the host: workspace
        // routes (served on a resolved Company host, where tenancy is initialized) go to the
        // workspace login; everything else (central platform hosts) goes to the platform
        // login. The app has no route named `login`; auth routes are namespaced.
        $middleware->redirectGuestsTo(function (Request $request): string {
            // Decide by the request host, not tenancy state: by the time this runs the
            // tenancy context may already be unwinding. A non-central host is a Company
            // host, so send the guest to the workspace login on that same host (route()
            // would resolve against the central app URL root, which is wrong here).
            $central = (array) config('tenancy.central_domains', []);

            if (! in_array($request->getHost(), $central, true)) {
                return $request->getSchemeAndHttpHost().'/login';
            }

            return route('platform.login');
        });

        // Already-authenticated users hitting a guest-only route land on their home:
        // workspace users on the workspace home, platform admins on the dashboard.
        $middleware->redirectUsersTo(function (Request $request): string {
            $central = (array) config('tenancy.central_domains', []);

            if (! in_array($request->getHost(), $central, true)) {
                return $request->getSchemeAndHttpHost().'/';
            }

            return route('platform.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
