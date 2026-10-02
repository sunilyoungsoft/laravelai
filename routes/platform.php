<?php

use App\Http\Controllers\Auth\PlatformLoginController;
use App\Http\Controllers\Platform\CompanyController;
use App\Http\Controllers\Platform\CompanyDomainController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\WorkspaceProvisionController;
use Illuminate\Support\Facades\Route;

/*
 * Platform admin routes. These are central (Platform) routes served on the `platform`
 * connection. They never run under tenant.resolve and never initialize Stancl tenancy.
 *
 * Authentication uses the `platform` guard (the application default). Guest-only routes
 * expose the login form and credential attempt; everything else requires an authenticated
 * Platform User via `auth:platform`.
 */

// Guest-only: already-authenticated platform admins are redirected away (to the dashboard).
Route::middleware('guest:platform')->group(function () {
    Route::get('/login', [PlatformLoginController::class, 'showLogin'])->name('platform.login');
    Route::post('/login', [PlatformLoginController::class, 'login']);
});

// Authenticated Platform admin area. Every action authorizes server-side via CompanyPolicy
// inside its controller (B2); route `can:` middleware is intentionally not used so there is a
// single source of authorization truth. The `{company}` parameter is implicitly bound: the
// Company model sets `$connection = 'platform'`, and these central routes never initialize
// Stancl tenancy (the default connection stays `platform`), so the binding always queries the
// platform connection and the SoftDeletes default scope excludes trashed companies.
Route::middleware('auth:platform')->group(function () {
    Route::post('/logout', [PlatformLoginController::class, 'logout'])->name('platform.logout');

    Route::get('/dashboard', DashboardController::class)->name('platform.dashboard');

    // Companies. `GET /companies/create` is registered before `GET /companies/{company}` so the
    // literal "create" segment is not captured as a {company} identifier.
    Route::get('/companies', [CompanyController::class, 'index'])->name('platform.companies.index');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('platform.companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('platform.companies.store');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('platform.companies.show');

    // Synchronous Workspace provisioning / retry (1E-E) and the single effective domain (1E-C).
    Route::post('/companies/{company}/provision', [WorkspaceProvisionController::class, 'store'])->name('platform.companies.provision');
    Route::post('/companies/{company}/domain', [CompanyDomainController::class, 'store'])->name('platform.companies.domain.store');
    Route::put('/companies/{company}/domain', [CompanyDomainController::class, 'update'])->name('platform.companies.domain.update');
});
