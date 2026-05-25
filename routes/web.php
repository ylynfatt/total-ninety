<?php

use App\Http\Controllers\GamesController;
use App\Http\Controllers\GroupsController;
use App\Http\Controllers\LeaguesController;
use App\Http\Controllers\SeasonsController;
use App\Http\Controllers\StagesController;
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

// Stages (nested under a season). Same create-before-show ordering.
Route::middleware('auth')->scopeBindings()->group(function () {
    Route::get('leagues/{league}/seasons/{season}/stages/create', [StagesController::class, 'create'])->name('stages.create');
    Route::post('leagues/{league}/seasons/{season}/stages', [StagesController::class, 'store'])->name('stages.store');
});

Route::get('leagues/{league}/seasons/{season}/stages/{stage}', [StagesController::class, 'show'])
    ->scopeBindings()
    ->name('stages.show');

Route::middleware('auth')->scopeBindings()->group(function () {
    Route::get('leagues/{league}/seasons/{season}/stages/{stage}/edit', [StagesController::class, 'edit'])->name('stages.edit');
    Route::put('leagues/{league}/seasons/{season}/stages/{stage}', [StagesController::class, 'update'])->name('stages.update');
    Route::patch('leagues/{league}/seasons/{season}/stages/{stage}', [StagesController::class, 'update']);
    Route::delete('leagues/{league}/seasons/{season}/stages/{stage}', [StagesController::class, 'destroy'])->name('stages.destroy');

    // Generate fixtures for a stage.
    Route::post(
        'leagues/{league}/seasons/{season}/stages/{stage}/generate-fixtures',
        [StagesController::class, 'generateFixtures']
    )->name('stages.generate-fixtures');
});

// Groups (nested under a stage). For GroupStage / Conference formats only,
// though we don't enforce that here — controller validation handles it.
Route::middleware('auth')->scopeBindings()->group(function () {
    Route::get('leagues/{league}/seasons/{season}/stages/{stage}/groups/create', [GroupsController::class, 'create'])->name('groups.create');
    Route::post('leagues/{league}/seasons/{season}/stages/{stage}/groups', [GroupsController::class, 'store'])->name('groups.store');
    Route::get('leagues/{league}/seasons/{season}/stages/{stage}/groups/{group}/edit', [GroupsController::class, 'edit'])->name('groups.edit');
    Route::put('leagues/{league}/seasons/{season}/stages/{stage}/groups/{group}', [GroupsController::class, 'update'])->name('groups.update');
    Route::patch('leagues/{league}/seasons/{season}/stages/{stage}/groups/{group}', [GroupsController::class, 'update']);
    Route::delete('leagues/{league}/seasons/{season}/stages/{stage}/groups/{group}', [GroupsController::class, 'destroy'])->name('groups.destroy');

    // Team picker for a group, constrained to the parent season's roster.
    Route::get('leagues/{league}/seasons/{season}/stages/{stage}/groups/{group}/teams', [GroupsController::class, 'editTeams'])->name('groups.teams.edit');
    Route::put('leagues/{league}/seasons/{season}/stages/{stage}/groups/{group}/teams', [GroupsController::class, 'syncTeams'])->name('groups.teams.sync');
});

// Legacy Blade-rendered resources — slated for removal in a later phase.
Route::resource('teams', TeamsController::class);
Route::resource('games', GamesController::class);

require __DIR__.'/settings.php';
