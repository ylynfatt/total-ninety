<?php

use App\Http\Controllers\GamesController;
use App\Http\Controllers\LeaguesController;
use App\Http\Controllers\TeamsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

// Leagues — public viewing (index/show), authenticated mutation. Slug-based
// route-model binding via League::getRouteKeyName().
Route::resource('leagues', LeaguesController::class)
    ->only(['index', 'show']);

Route::resource('leagues', LeaguesController::class)
    ->except(['index', 'show'])
    ->middleware('auth');

// Legacy Blade-rendered resources — slated for removal in a later phase.
Route::resource('teams', TeamsController::class);
Route::resource('games', GamesController::class);

require __DIR__.'/settings.php';
