<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;

class TeamsController extends Controller
{
    function index(Request $request)
    {
        $teams = Team::all();

        return view('teams.index', [
            'teams' => $teams
        ]);
    }

    public function create(Request $request)
    {
        return view('teams.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'acronym' => 'required|min:3|max:3',
            'year_founded' => 'required|int',
            'home_ground' => 'required',
        ]);

        $team = new Team();
        $team->name = $validated['name'];
        $team->acronym = $validated['acronym'];
        $team->year_founded = $validated['year_founded'];
        $team->home_ground = $validated['home_ground'];
        $team->save();

        return redirect()
            ->route('teams.index')
            ->with('status', 'Team added successfully!');
    }

    public function show(Team $team)
    {
        return view('teams.show', [
            'team' => $team
        ]);
    }

    public function edit(Team $team)
    {
        return view('teams.edit', [
            'team' => $team
        ]);
    }

    public function update(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'required',
            'acronym' => 'required|min:3|max:3',
            'year_founded' => 'required|int',
            'home_ground' => 'required',
        ]);

        $team->update($validated);

        return redirect()
            ->route('teams.show', [$team->id])
            ->with('status', 'Team updated successfully!');
    }

    public function destroy(Team $team)
    {

    }
}
