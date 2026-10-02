<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InitializeTenancyByResolvedDomain;
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
            // group so they get sessions, CSRF, and the shared Inertia props.
            Route::middleware('web')
                ->group(__DIR__.'/../routes/platform.php');
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

        // Unauthenticated Platform admin requests redirect to the platform login page
        // (the app has no route named `login`; auth routes are namespaced `platform.*`).
        $middleware->redirectGuestsTo(fn (Request $request) => route('platform.login'));

        // Already-authenticated admins hitting a guest-only route land on the dashboard.
        $middleware->redirectUsersTo(fn (Request $request) => route('platform.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
