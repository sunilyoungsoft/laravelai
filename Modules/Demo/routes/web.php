<?php

use Illuminate\Support\Facades\Route;
use Modules\Demo\Http\Controllers\DemoNoteController;

Route::get('/demo-notes/create', [DemoNoteController::class, 'create'])
    ->name('demo-notes.create');

Route::post('/demo-notes', [DemoNoteController::class, 'store'])
    ->name('demo-notes.store');
