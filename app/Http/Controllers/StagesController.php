<?php

namespace App\Http\Controllers;

use App\Actions\GenerateFixtures;
use App\Enums\StageFormat;
use App\Http\Requests\StoreStageRequest;
use App\Http\Requests\UpdateStageRequest;
use App\Models\League;
use App\Models\Season;
use App\Models\Stage;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StagesController extends Controller
{
    public function create(League $league, Season $season): Response
    {
        $this->ensureSeasonInLeague($league, $season);
        $this->authorize('update', $season);

        return Inertia::render('Stages/Create', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season->only(['id', 'name']),
            'formats' => $this->formatOptions(),
        ]);
    }

    public function store(StoreStageRequest $request, League $league, Season $season): RedirectResponse
    {
        $this->ensureSeasonInLeague($league, $season);
        $this->authorize('update', $season);

        $stage = $season->stages()->create($request->validated());

        return redirect()
            ->route('stages.show', [$league, $season, $stage])
            ->with('status', "Stage \"{$stage->name}\" created.");
    }

    public function show(League $league, Season $season, Stage $stage): Response
    {
        $this->ensureSeasonInLeague($league, $season);
        $this->ensureStageInSeason($season, $stage);
        $this->authorize('view', $stage);

        $stage->load([
            'groups' => fn ($q) => $q->withCount('teams'),
            'games' => fn ($q) => $q->orderBy('match_date'),
            'games.homeTeam:id,name,acronym',
            'games.awayTeam:id,name,acronym',
        ]);

        return Inertia::render('Stages/Show', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season->only(['id', 'name']),
            'stage' => $stage,
            'can' => [
                'update' => request()->user()?->can('update', $stage) ?? false,
                'delete' => request()->user()?->can('delete', $stage) ?? false,
            ],
        ]);
    }

    public function edit(League $league, Season $season, Stage $stage): Response
    {
        $this->ensureSeasonInLeague($league, $season);
        $this->ensureStageInSeason($season, $stage);
        $this->authorize('update', $stage);

        return Inertia::render('Stages/Edit', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season->only(['id', 'name']),
            'stage' => $stage,
            'formats' => $this->formatOptions(),
        ]);
    }

    public function update(UpdateStageRequest $request, League $league, Season $season, Stage $stage): RedirectResponse
    {
        $this->ensureSeasonInLeague($league, $season);
        $this->ensureStageInSeason($season, $stage);

        $stage->update($request->validated());

        return redirect()
            ->route('stages.show', [$league, $season, $stage])
            ->with('status', "Stage \"{$stage->name}\" updated.");
    }

    public function destroy(League $league, Season $season, Stage $stage): RedirectResponse
    {
        $this->ensureSeasonInLeague($league, $season);
        $this->ensureStageInSeason($season, $stage);
        $this->authorize('delete', $stage);

        $name = $stage->name;
        $stage->delete();

        return redirect()
            ->route('seasons.show', [$league, $season])
            ->with('status', "Stage \"{$name}\" deleted.");
    }

    /**
     * Persist the fixture set for this stage via the GenerateFixtures action.
     * Surfaces DomainException messages from the action as session errors so
     * the Inertia page can display them inline.
     */
    public function generateFixtures(League $league, Season $season, Stage $stage, GenerateFixtures $action): RedirectResponse
    {
        $this->ensureSeasonInLeague($league, $season);
        $this->ensureStageInSeason($season, $stage);
        $this->authorize('update', $stage);

        try {
            $games = $action->execute($stage);
        } catch (DomainException $e) {
            return back()->withErrors(['fixtures' => $e->getMessage()]);
        }

        return redirect()
            ->route('stages.show', [$league, $season, $stage])
            ->with('status', "Generated {$games->count()} fixtures.");
    }

    /**
     * @return array<int, array{value: string, label: string, hasGroups: bool, isBracket: bool}>
     */
    private function formatOptions(): array
    {
        return collect(StageFormat::cases())->map(fn (StageFormat $format) => [
            'value' => $format->value,
            'label' => $format->label(),
            'hasGroups' => $format->hasGroups(),
            'isBracket' => $format->isBracket(),
        ])->all();
    }

    private function ensureSeasonInLeague(League $league, Season $season): void
    {
        abort_if($season->league_id !== $league->id, 404);
    }

    private function ensureStageInSeason(Season $season, Stage $stage): void
    {
        abort_if($stage->season_id !== $season->id, 404);
    }
}
