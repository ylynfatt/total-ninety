<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamsController extends Controller
{
    public function index(Request $request): View
    {
        $teams = Team::all();

        return view('teams.index', [
            'teams' => $teams,
        ]);
    }

    public function create(Request $request): View
    {
        return view('teams.create');
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        Team::create($request->validated());

        return redirect()
            ->route('teams.index')
            ->with('status', 'Team added successfully!');
    }

    public function show(Team $team): View
    {
        $team->load(['homeGames', 'awayGames']);

        return view('teams.show', [
            'team' => $team,
        ]);
    }

    public function edit(Team $team): View
    {
        return view('teams.edit', [
            'team' => $team,
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return redirect()
            ->route('teams.show', $team)
            ->with('status', 'Team updated successfully!');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        return redirect()
            ->route('teams.index')
            ->with('status', 'Team deleted successfully!');
    }
}
