<?php

namespace App\Http\Controllers;

use App\Actions\GenerateFixtures;
use App\Domain\Standings\StandingsRegistry;
use App\Enums\StageFormat;
use App\Http\Requests\StoreStageRequest;
use App\Http\Requests\UpdateStageRequest;
use App\Models\Game;
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

    public function show(League $league, Season $season, Stage $stage, StandingsRegistry $standings): Response
    {
        $this->ensureSeasonInLeague($league, $season);
        $this->ensureStageInSeason($season, $stage);
        $this->authorize('view', $stage);

        $stage->load([
            'groups' => fn ($q) => $q->withCount('teams'),
            'groups.teams:id,name,acronym',
            'games' => fn ($q) => $q->orderBy('match_date'),
            'games.homeTeam:id,name,acronym',
            'games.awayTeam:id,name,acronym',
            'games.result',
            'season.teams:id,name,acronym',
        ]);

        return Inertia::render('Stages/Show', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season->only(['id', 'name']),
            'stage' => $stage,
            'standings' => $this->buildStandings($stage, $standings),
            'bracket' => $stage->format->isBracket() ? $this->buildBracket($stage) : null,
            'can' => [
                'update' => request()->user()?->can('update', $stage) ?? false,
                'delete' => request()->user()?->can('delete', $stage) ?? false,
            ],
        ]);
    }

    /**
     * Resolve the standings payload for this stage.
     *
     *   - Bracket formats: null (no table; the UI shows a bracket later).
     *   - Ungrouped table formats (RoundRobin Single/Double): one StandingRow[]
     *     under the `overall` key.
     *   - Grouped table formats (GroupStage / Conference): per-group
     *     StandingRow[] keyed by group id.
     *
     * @return null|array{overall: array<int, array<string, mixed>>}|array<string, array{group: array{id:int, name:string}, rows: array<int, array<string, mixed>>}>
     */
    private function buildStandings(Stage $stage, StandingsRegistry $standings): ?array
    {
        if (! $standings->supports($stage->format)) {
            return null;
        }

        $calculator = $standings->for($stage->format);

        if ($stage->format->hasGroups()) {
            $byGroup = [];
            foreach ($stage->groups as $group) {
                $byGroup[(string) $group->id] = [
                    'group' => ['id' => $group->id, 'name' => $group->name],
                    'rows' => $calculator->calculate($stage, $group)
                        ->map(fn ($row) => $row->toArray())
                        ->all(),
                ];
            }

            return $byGroup === [] ? null : $byGroup;
        }

        return [
            'overall' => $calculator->calculate($stage)
                ->map(fn ($row) => $row->toArray())
                ->all(),
        ];
    }

    /**
     * Shape the knockout games into ordered rounds for the bracket view. Each
     * round carries a human label (Final / Semifinals / …) derived from its
     * slot count and its games sorted by bracket_position.
     *
     * @return null|array<int, array{round: int, label: string, games: array<int, array<string, mixed>>}>
     */
    private function buildBracket(Stage $stage): ?array
    {
        $byRound = $stage->games
            ->whereNotNull('round')
            ->sortBy([['round', 'asc'], ['bracket_position', 'asc']])
            ->groupBy('round');

        if ($byRound->isEmpty()) {
            return null;
        }

        return $byRound
            ->map(fn ($games, $round) => [
                'round' => (int) $round,
                'label' => $this->roundLabel($games->count()),
                'games' => $games->values()->map(fn ($game) => [
                    'id' => $game->id,
                    'bracket_position' => $game->bracket_position,
                    'home_team' => $game->homeTeam?->only(['id', 'name', 'acronym']),
                    'away_team' => $game->awayTeam?->only(['id', 'name', 'acronym']),
                    'home_team_score' => $game->result?->home_team_score,
                    'away_team_score' => $game->result?->away_team_score,
                    'status' => $game->status->value,
                    'winner' => $this->winnerSide($game),
                ])->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * 'home' / 'away' once a game is decided, otherwise null.
     */
    private function winnerSide(Game $game): ?string
    {
        $result = $game->result;

        if ($result === null || $result->home_team_score === $result->away_team_score) {
            return null;
        }

        return $result->home_team_score > $result->away_team_score ? 'home' : 'away';
    }

    private function roundLabel(int $gameCount): string
    {
        return match ($gameCount) {
            1 => 'Final',
            2 => 'Semifinals',
            4 => 'Quarterfinals',
            8 => 'Round of 16',
            16 => 'Round of 32',
            default => 'Round of '.($gameCount * 2),
        };
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
