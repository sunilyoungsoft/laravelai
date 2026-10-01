<?php

use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('welcome');

/*
 * Workspace-facing routes. The tenant.resolve middleware resolves the request hostname to a
 * Company via the Platform domains table and initializes Stancl tenancy before these routes
 * run. Central domains pass through elsewhere; these routes require a resolved tenant.
 */
Route::middleware('tenant.resolve')->group(function () {
    Route::get('/workspace/ping', function () {
        return response()->json([
            'tenant' => tenant()?->getTenantKey(),
            'database' => DB::connection()->getDatabaseName(),
        ]);
    })->name('workspace.ping');
});
