<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Group;
use App\Models\League;
use App\Models\Season;
use App\Models\Stage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupsController extends Controller
{
    public function create(League $league, Season $season, Stage $stage): Response
    {
        $this->ensureChain($league, $season, $stage);
        $this->authorize('update', $stage);

        return Inertia::render('Groups/Create', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season->only(['id', 'name']),
            'stage' => $stage->only(['id', 'name']),
        ]);
    }

    public function store(StoreGroupRequest $request, League $league, Season $season, Stage $stage): RedirectResponse
    {
        $this->ensureChain($league, $season, $stage);
        $this->authorize('update', $stage);

        $group = $stage->groups()->create($request->validated());

        return redirect()
            ->route('stages.show', [$league, $season, $stage])
            ->with('status', "Group \"{$group->name}\" created.");
    }

    public function edit(League $league, Season $season, Stage $stage, Group $group): Response
    {
        $this->ensureChain($league, $season, $stage, $group);
        $this->authorize('update', $stage);

        return Inertia::render('Groups/Edit', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season->only(['id', 'name']),
            'stage' => $stage->only(['id', 'name']),
            'group' => $group,
        ]);
    }

    public function update(UpdateGroupRequest $request, League $league, Season $season, Stage $stage, Group $group): RedirectResponse
    {
        $this->ensureChain($league, $season, $stage, $group);

        $group->update($request->validated());

        return redirect()
            ->route('stages.show', [$league, $season, $stage])
            ->with('status', "Group \"{$group->name}\" updated.");
    }

    public function destroy(League $league, Season $season, Stage $stage, Group $group): RedirectResponse
    {
        $this->ensureChain($league, $season, $stage, $group);
        $this->authorize('update', $stage);

        $name = $group->name;
        $group->delete();

        return redirect()
            ->route('stages.show', [$league, $season, $stage])
            ->with('status', "Group \"{$name}\" deleted.");
    }

    /**
     * Manage the team roster for this group (picker UI). Teams to pick from
     * are scoped to the parent season's roster — you can't put a team into
     * a group if it's not in the season.
     */
    public function editTeams(League $league, Season $season, Stage $stage, Group $group): Response
    {
        $this->ensureChain($league, $season, $stage, $group);
        $this->authorize('update', $stage);

        return Inertia::render('Groups/ManageTeams', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season->only(['id', 'name']),
            'stage' => $stage->only(['id', 'name']),
            'group' => $group->only(['id', 'name']),
            'teams' => $season->teams()->orderBy('name')->get(['teams.id', 'teams.name', 'teams.acronym']),
            'attached_team_ids' => $group->teams()->pluck('teams.id')->all(),
        ]);
    }

    /**
     * Bulk-sync the group's team roster. The valid set of team ids is
     * constrained to teams already attached to the parent season —
     * validation rejects anything outside that set.
     */
    public function syncTeams(Request $request, League $league, Season $season, Stage $stage, Group $group): RedirectResponse
    {
        $this->ensureChain($league, $season, $stage, $group);
        $this->authorize('update', $stage);

        $seasonTeamIds = $season->teams()->pluck('teams.id')->all();

        $validated = $request->validate([
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'in:'.implode(',', $seasonTeamIds ?: [0])],
        ], [
            'team_ids.*.in' => 'Only teams already in the season can be assigned to a group.',
        ]);

        $group->teams()->sync($validated['team_ids'] ?? []);

        return redirect()
            ->route('stages.show', [$league, $season, $stage])
            ->with('status', "\"{$group->name}\" roster updated.");
    }

    private function ensureChain(League $league, Season $season, Stage $stage, ?Group $group = null): void
    {
        abort_if($season->league_id !== $league->id, 404);
        abort_if($stage->season_id !== $season->id, 404);

        if ($group !== null) {
            abort_if($group->stage_id !== $stage->id, 404);
        }
    }
}
