<?php

use Illuminate\Support\Facades\Route;
use Modules\Demo\Http\Controllers\Api\DemoNoteApiController;

/*
| Temporary architecture-proof API route.
| Production APIs should use the /api/v1 convention later.
*/
Route::post('/demo-notes', [DemoNoteApiController::class, 'store'])
    ->name('api.demo-notes.store');
