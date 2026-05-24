<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSeasonRequest;
use App\Http\Requests\UpdateSeasonRequest;
use App\Models\League;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SeasonsController extends Controller
{
    public function create(League $league): Response
    {
        $this->authorize('update', $league);

        return Inertia::render('Seasons/Create', [
            'league' => $league->only(['id', 'name', 'slug']),
        ]);
    }

    public function store(StoreSeasonRequest $request, League $league): RedirectResponse
    {
        $this->authorize('update', $league);

        $season = $league->seasons()->create($request->validated());

        return redirect()
            ->route('seasons.show', [$league, $season])
            ->with('status', "Season \"{$season->name}\" created.");
    }

    public function show(League $league, Season $season): Response
    {
        $this->ensureScoped($league, $season);
        $this->authorize('view', $season);

        $season->load(['teams:id,name,acronym', 'stages']);

        return Inertia::render('Seasons/Show', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season,
            'can' => [
                'update' => request()->user()?->can('update', $season) ?? false,
                'delete' => request()->user()?->can('delete', $season) ?? false,
            ],
        ]);
    }

    public function edit(League $league, Season $season): Response
    {
        $this->ensureScoped($league, $season);
        $this->authorize('update', $season);

        return Inertia::render('Seasons/Edit', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season,
        ]);
    }

    public function update(UpdateSeasonRequest $request, League $league, Season $season): RedirectResponse
    {
        $this->ensureScoped($league, $season);

        $season->update($request->validated());

        return redirect()
            ->route('seasons.show', [$league, $season])
            ->with('status', "Season \"{$season->name}\" updated.");
    }

    public function destroy(League $league, Season $season): RedirectResponse
    {
        $this->ensureScoped($league, $season);
        $this->authorize('delete', $season);

        $name = $season->name;
        $season->delete();

        return redirect()
            ->route('leagues.show', $league)
            ->with('status', "Season \"{$name}\" deleted.");
    }

    /**
     * Manage the team roster for a season (picker UI).
     */
    public function editTeams(League $league, Season $season): Response
    {
        $this->ensureScoped($league, $season);
        $this->authorize('update', $season);

        return Inertia::render('Seasons/ManageTeams', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season->only(['id', 'name']),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name', 'acronym']),
            'attached_team_ids' => $season->teams()->pluck('teams.id')->all(),
        ]);
    }

    /**
     * Bulk-sync the season's team roster.
     */
    public function syncTeams(Request $request, League $league, Season $season): RedirectResponse
    {
        $this->ensureScoped($league, $season);
        $this->authorize('update', $season);

        $validated = $request->validate([
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
        ]);

        $season->teams()->sync($validated['team_ids'] ?? []);

        return redirect()
            ->route('seasons.show', [$league, $season])
            ->with('status', 'Team roster updated.');
    }

    /**
     * Guard against /leagues/A/seasons/{id-belonging-to-B} drift.
     * Defensive even though route binding scoping would also enforce it.
     */
    private function ensureScoped(League $league, Season $season): void
    {
        abort_if($season->league_id !== $league->id, 404);
    }
}
