<?php

use App\Http\Controllers\Workspace\WorkspaceLoginController;
use App\Http\Controllers\Workspace\WorkspacePasswordChangeController;
use App\Http\Middleware\EnsureWorkspacePasswordChanged;
use App\Http\Middleware\InitializeTenancyByResolvedDomain;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * Workspace (tenant) routes. Served only on a resolved Company host: the
 * `tenant.resolve` middleware maps the request hostname to an active Company and
 * initializes Stancl tenancy before any of these routes run, so authentication uses
 * the `workspace` guard against that Company's own database (1F-C, 1F-D).
 *
 * These routes are intentionally NOT host-scoped to a fixed list — any non-central
 * host that resolves to an active Company is served here. Central platform hosts are
 * served by routes/platform.php instead.
 */
Route::middleware(InitializeTenancyByResolvedDomain::class)->group(function () {
    // Guest-only: an authenticated workspace user is redirected to the workspace home.
    Route::middleware('guest:workspace')->group(function () {
        Route::get('/login', [WorkspaceLoginController::class, 'showLogin'])->name('workspace.login');
        Route::post('/login', [WorkspaceLoginController::class, 'login']);
    });

    // Authenticated workspace area. EnsureWorkspacePasswordChanged forces a user flagged
    // must_change_password to the change page before anything else (except logout).
    Route::middleware(['auth:workspace', EnsureWorkspacePasswordChanged::class])->group(function () {
        Route::post('/logout', [WorkspaceLoginController::class, 'logout'])->name('workspace.logout');

        Route::get('/password/change', [WorkspacePasswordChangeController::class, 'show'])
            ->name('workspace.password.change');
        Route::post('/password/change', [WorkspacePasswordChangeController::class, 'update'])
            ->name('workspace.password.update');

        // Workspace landing. The real Inertia page is built in the next task; the route
        // name must resolve now because login + password change redirect to it.
        Route::get('/', fn () => Inertia::render('Workspace/Home'))->name('workspace.home');
    });
});
