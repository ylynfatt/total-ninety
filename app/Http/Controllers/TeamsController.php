<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teams remain discoverable across leagues; only their creator may mutate
 * them. Existing unowned shared teams remain read-only until assigned.
 */
class TeamsController extends Controller
{
    public function index(): Response
    {
        $teams = Team::query()
            ->orderBy('name')
            ->withCount('seasons')
            ->get(['id', 'name', 'acronym', 'home_ground', 'year_founded']);

        return Inertia::render('Teams/Index', [
            'teams' => $teams,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Team::class);

        return Inertia::render('Teams/Create');
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $team = Team::create([...$request->validated(), 'user_id' => $request->user()->id]);

        return redirect()
            ->route('teams.show', $team)
            ->with('status', "Team \"{$team->name}\" added.");
    }

    public function show(Team $team): Response
    {
        $team->load([
            'seasons:id,league_id,name',
            'seasons.league:id,name,slug',
        ]);

        return Inertia::render('Teams/Show', [
            'team' => $team,
            'can' => [
                'update' => request()->user()?->can('update', $team) ?? false,
                'delete' => (request()->user()?->can('delete', $team) ?? false)
                    && ! $team->seasons()->exists() && ! $team->homeGames()->exists() && ! $team->awayGames()->exists(),
            ],
        ]);
    }

    public function edit(Team $team): Response
    {
        $this->authorize('update', $team);

        return Inertia::render('Teams/Edit', [
            'team' => $team,
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return redirect()
            ->route('teams.show', $team)
            ->with('status', "Team \"{$team->name}\" updated.");
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        if ($team->seasons()->exists() || $team->homeGames()->exists() || $team->awayGames()->exists()) {
            return back()->withErrors([
                'delete' => "\"{$team->name}\" is used by a season or fixture and cannot be deleted.",
            ]);
        }

        $name = $team->name;
        $team->delete();

        return redirect()
            ->route('teams.index')
            ->with('status', "Team \"{$name}\" deleted.");
    }
}
