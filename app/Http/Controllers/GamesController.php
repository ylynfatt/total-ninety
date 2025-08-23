<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameRequest;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GamesController extends Controller
{
    /**
     * Display a listing of upcoming games.
     */
    public function index(): View
    {
        $games = Game::with(['homeTeam', 'awayTeam'])
            ->orderBy('match_date', 'asc')
            ->get();

        return view('games.index', ['games' => $games]);
    }

    /**
     * Show the form for adding a new game.
     */
    public function create(): View
    {
        $teams = Team::all(['id', 'name']);

        return view('games.create', [
            'teams' => $teams,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGameRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Game::create([
            'home_team_id' => $validated['home_team'],
            'away_team_id' => $validated['away_team'],
            'match_date' => $validated['match_date'],
            'location' => $validated['location'],
        ]);

        return redirect()
            ->route('games.index')
            ->with('status', 'Game added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Game $game): View
    {
        $game->load(['homeTeam', 'awayTeam', 'result']);

        return view('games.show', ['game' => $game]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Game $game): View
    {
        $teams = Team::all(['id', 'name']);

        return view('games.edit', [
            'game' => $game,
            'teams' => $teams,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'home_team' => ['required', 'integer', 'exists:teams,id'],
            'away_team' => ['required', 'integer', 'exists:teams,id', 'different:home_team'],
            'match_date' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        $game->update([
            'home_team_id' => $validated['home_team'],
            'away_team_id' => $validated['away_team'],
            'match_date' => $validated['match_date'],
            'location' => $validated['location'],
        ]);

        return redirect()
            ->route('games.show', $game)
            ->with('status', 'Game updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Game $game): RedirectResponse
    {
        $game->delete();

        return redirect()
            ->route('games.index')
            ->with('status', 'Game deleted successfully!');
    }
}
