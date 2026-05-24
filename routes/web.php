<?php

use App\Http\Controllers\GamesController;
use App\Http\Controllers\TeamsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

// Legacy Blade-rendered resources — slated for an Inertia rewrite in a later phase.
Route::resource('teams', TeamsController::class);
Route::resource('games', GamesController::class);

require __DIR__.'/settings.php';
