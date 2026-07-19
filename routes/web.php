<?php

use App\Http\Controllers\GamecastController;
use App\Http\Controllers\GameControlController;
use App\Http\Controllers\GameFixturesController;
use App\Http\Controllers\GroupsController;
use App\Http\Controllers\LeaguesController;
use App\Http\Controllers\ScoreboardController;
use App\Http\Controllers\SeasonsController;
use App\Http\Controllers\StagesController;
use App\Http\Controllers\TeamsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

// Public live scoreboard — all in-progress games across every league.
Route::get('scoreboard', [ScoreboardController::class, 'index'])->name('scoreboard.index');

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

    // Fill a knockout stage's round-1 games from the previous grouped
    // stage's standings, per the stage's entrant slot descriptors.
    Route::post(
        'leagues/{league}/seasons/{season}/stages/{stage}/seed-from-groups',
        [StagesController::class, 'seedFromGroups']
    )->name('stages.seed-from-groups');
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

// Public gamecast for a single fixture — live score + event timeline.
Route::get(
    'leagues/{league}/seasons/{season}/stages/{stage}/games/{game}',
    [GamecastController::class, 'show']
)->scopeBindings()->name('games.show');

// Game fixtures inside a stage — schedule editor + result entry.
Route::middleware('auth')->scopeBindings()->group(function () {
    Route::get(
        'leagues/{league}/seasons/{season}/stages/{stage}/games/{game}/edit',
        [GameFixturesController::class, 'edit']
    )->name('fixtures.edit');

    Route::patch(
        'leagues/{league}/seasons/{season}/stages/{stage}/games/{game}/schedule',
        [GameFixturesController::class, 'updateSchedule']
    )->name('fixtures.schedule.update');

    Route::put(
        'leagues/{league}/seasons/{season}/stages/{stage}/games/{game}/result',
        [GameFixturesController::class, 'storeResult']
    )->name('fixtures.result.store');

    Route::delete(
        'leagues/{league}/seasons/{season}/stages/{stage}/games/{game}/result',
        [GameFixturesController::class, 'destroyResult']
    )->name('fixtures.result.destroy');

    // Live gamecast controls — status transitions + timeline event entry.
    Route::patch(
        'leagues/{league}/seasons/{season}/stages/{stage}/games/{game}/status',
        [GameControlController::class, 'updateStatus']
    )->name('games.status.update');

    Route::post(
        'leagues/{league}/seasons/{season}/stages/{stage}/games/{game}/events',
        [GameControlController::class, 'storeEvent']
    )->name('games.events.store');

    Route::patch(
        'leagues/{league}/seasons/{season}/stages/{stage}/games/{game}/events/{event}',
        [GameControlController::class, 'updateEvent']
    )->name('games.events.update');

    Route::delete(
        'leagues/{league}/seasons/{season}/stages/{stage}/games/{game}/events/{event}',
        [GameControlController::class, 'destroyEvent']
    )->name('games.events.destroy');
});

// Teams — public viewing (index/show), authenticated mutation.
// Same create-before-show ordering trick as /leagues.
Route::get('teams', [TeamsController::class, 'index'])->name('teams.index');

Route::middleware('auth')->group(function () {
    Route::get('teams/create', [TeamsController::class, 'create'])->name('teams.create');
    Route::post('teams', [TeamsController::class, 'store'])->name('teams.store');
});

Route::get('teams/{team}', [TeamsController::class, 'show'])->name('teams.show');

Route::middleware('auth')->group(function () {
    Route::get('teams/{team}/edit', [TeamsController::class, 'edit'])->name('teams.edit');
    Route::put('teams/{team}', [TeamsController::class, 'update'])->name('teams.update');
    Route::patch('teams/{team}', [TeamsController::class, 'update']);
    Route::delete('teams/{team}', [TeamsController::class, 'destroy'])->name('teams.destroy');
});

require __DIR__.'/settings.php';
