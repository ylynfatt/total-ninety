<?php

use App\Http\Controllers\GamesController;
use App\Http\Controllers\LeaguesController;
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

// Legacy Blade-rendered resources — slated for removal in a later phase.
Route::resource('teams', TeamsController::class);
Route::resource('games', GamesController::class);

require __DIR__.'/settings.php';
