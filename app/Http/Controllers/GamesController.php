<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;

class GamesController extends Controller
{
    /**
     * Display a listing of upcoming games.
     */
    public function index()
    {
        $games = Game::orderBy('match_date', 'asc')->get();

        return view('games.index', ['games' => $games]);
    }

    /**
     * Show the form for adding a new game.
     */
    public function create()
    {
        $teams = Team::all(['id', 'name']);

        return view('games.create', [
            'teams' => $teams,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'home_team' => ['required', 'integer'],
            'away_team' => ['required', 'integer'],
            'match_date' => ['required', 'date'],
            'location' => ['required'],
        ]);

        Game::create([
            'home_team_id' => $validated['home_team'],
            'away_team_id' => $validated['away_team'],
            'match_date' => $validated['match_date'],
            'location' => $validated['location'],
        ]);

        return redirect()->route('games.index')->with('status', 'Game added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Game $game)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Game $game)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Game $game)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Game $game)
    {
        //
    }
}
