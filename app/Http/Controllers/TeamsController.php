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

        session()->flash('Team added successfully!');

        return redirect()->route('teams.index');
    }

    public function show(Team $team)
    {

    }

    public function edit(Team $team)
    {

    }

    public function update(Team $team)
    {

    }

    public function destroy(Team $team)
    {

    }
}
