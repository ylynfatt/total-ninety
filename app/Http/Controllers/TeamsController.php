<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teams admin (Inertia).
 *
 * Trust model for now: teams are a globally-shared resource. Anyone
 * signed in can create / edit / delete any team. There's no per-team
 * ownership column yet — if usage outgrows this, a follow-up PR can
 * add a `user_id` + a TeamPolicy.
 *
 * Destroy is intentionally conservative: a team that's already
 * attached to one or more seasons can't be deleted (the FK
 * restrictOnDelete on games would error out anyway). We catch that
 * case explicitly so the user gets a friendly message instead of a
 * 500.
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
        return Inertia::render('Teams/Create');
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $team = Team::create($request->validated());

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
                'update' => request()->user() !== null,
                'delete' => request()->user() !== null && $team->seasons->isEmpty(),
            ],
        ]);
    }

    public function edit(Team $team): Response
    {
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
        if ($team->seasons()->exists()) {
            return back()->withErrors([
                'delete' => "\"{$team->name}\" is attached to one or more seasons. Detach it from those seasons first.",
            ]);
        }

        $name = $team->name;
        $team->delete();

        return redirect()
            ->route('teams.index')
            ->with('status', "Team \"{$name}\" deleted.");
    }
}
