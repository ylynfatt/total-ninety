<?php

use App\Http\Controllers\GamesController;
use App\Http\Controllers\LeaguesController;
use App\Http\Controllers\SeasonsController;
use App\Http\Controllers\TeamsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

// Leagues — public viewing (index/show), authenticated mutation.
// Slug-based route-model binding via League::getRouteKeyName().
// Note: /leagues/create MUST be registered before /leagues/{league} or
// `create` gets treated as a slug. Hence the explicit declarations.
Route::get('leagues', [LeaguesController::class, 'index'])->name('leagues.index');

Route::middleware('auth')->group(function () {
    Route::get('leagues/create', [LeaguesController::class, 'create'])->name('leagues.create');
    Route::post('leagues', [LeaguesController::class, 'store'])->name('leagues.store');
});

Route::get('leagues/{league}', [LeaguesController::class, 'show'])->name('leagues.show');

Route::middleware('auth')->group(function () {
    Route::get('leagues/{league}/edit', [LeaguesController::class, 'edit'])->name('leagues.edit');
    Route::put('leagues/{league}', [LeaguesController::class, 'update'])->name('leagues.update');
    Route::patch('leagues/{league}', [LeaguesController::class, 'update']);
    Route::delete('leagues/{league}', [LeaguesController::class, 'destroy'])->name('leagues.destroy');
});

// Seasons (nested under a league). Same create-before-show ordering rule.
Route::middleware('auth')->group(function () {
    Route::get('leagues/{league}/seasons/create', [SeasonsController::class, 'create'])->name('seasons.create');
    Route::post('leagues/{league}/seasons', [SeasonsController::class, 'store'])->name('seasons.store');
});

Route::get('leagues/{league}/seasons/{season}', [SeasonsController::class, 'show'])
    ->scopeBindings()
    ->name('seasons.show');

Route::middleware('auth')->scopeBindings()->group(function () {
    Route::get('leagues/{league}/seasons/{season}/edit', [SeasonsController::class, 'edit'])->name('seasons.edit');
    Route::put('leagues/{league}/seasons/{season}', [SeasonsController::class, 'update'])->name('seasons.update');
    Route::patch('leagues/{league}/seasons/{season}', [SeasonsController::class, 'update']);
    Route::delete('leagues/{league}/seasons/{season}', [SeasonsController::class, 'destroy'])->name('seasons.destroy');

    // Team roster picker for the season.
    Route::get('leagues/{league}/seasons/{season}/teams', [SeasonsController::class, 'editTeams'])->name('seasons.teams.edit');
    Route::put('leagues/{league}/seasons/{season}/teams', [SeasonsController::class, 'syncTeams'])->name('seasons.teams.sync');
});

// Legacy Blade-rendered resources — slated for removal in a later phase.
Route::resource('teams', TeamsController::class);
Route::resource('games', GamesController::class);

require __DIR__.'/settings.php';
