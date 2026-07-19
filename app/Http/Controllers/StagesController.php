<?php

namespace App\Http\Controllers;

use App\Actions\GenerateFixtures;
use App\Actions\SeedStageFromGroups;
use App\Domain\Formats\EntrantSlot;
use App\Domain\Standings\BestPlacedCalculator;
use App\Domain\Standings\StandingsRegistry;
use App\Enums\GameStatus;
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

    public function show(League $league, Season $season, Stage $stage, StandingsRegistry $standings, BestPlacedCalculator $bestPlaced): Response
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
            'bestPlaced' => $this->buildBestPlaced($stage, $bestPlaced),
            'bracket' => $stage->format->isBracket() ? $this->buildBracket($stage) : null,
            'seeding' => $this->buildSeeding($stage),
            'can' => [
                'update' => request()->user()?->can('update', $stage) ?? false,
                'delete' => request()->user()?->can('delete', $stage) ?? false,
            ],
        ]);
    }

    /**
     * Seeding review payload for the admin: every entrant slot resolved to
     * a concrete team from the source stage's current standings, so they can
     * confirm before the bracket fills. Null for viewers, non-entrant stages,
     * or stages without generated round-1 games.
     *
     * @return null|array{
     *     source: array{id: int, name: string},
     *     source_complete: bool,
     *     seeded: bool,
     *     can_apply: bool,
     *     error: string|null,
     *     slots: array<int, array{label: string, team: array{id: int, name: string, acronym: string}|null, error: string|null}>,
     * }
     */
    private function buildSeeding(Stage $stage): ?array
    {
        if (! $stage->format->isBracket() || ! (request()->user()?->can('update', $stage) ?? false)) {
            return null;
        }

        if ($this->entrantSlots($stage) === []) {
            return null;
        }

        $roundOne = $stage->games->where('round', 1);

        if ($roundOne->isEmpty()) {
            return null;
        }

        try {
            $preview = app(SeedStageFromGroups::class)->preview($stage);
        } catch (DomainException $e) {
            return [
                'source' => ['id' => 0, 'name' => ''],
                'source_complete' => false,
                'seeded' => false,
                'can_apply' => false,
                'error' => $e->getMessage(),
                'slots' => [],
            ];
        }

        $allResolved = collect($preview['slots'])->every(fn (array $slot) => $slot['error'] === null);
        $allScheduled = $roundOne->every(fn (Game $game) => $game->status === GameStatus::Scheduled);

        return [
            ...$preview,
            'seeded' => $roundOne->contains(fn (Game $game) => $game->home_team_id !== null || $game->away_team_id !== null),
            'can_apply' => $allResolved && $allScheduled,
            'error' => $allScheduled ? null : 'A round-1 game has already started; the bracket can no longer be re-seeded.',
        ];
    }

    /**
     * Apply the entrant seeding after the admin confirms the preview.
     */
    public function seedFromGroups(League $league, Season $season, Stage $stage, SeedStageFromGroups $action): RedirectResponse
    {
        $this->ensureSeasonInLeague($league, $season);
        $this->ensureStageInSeason($season, $stage);
        $this->authorize('update', $stage);

        try {
            $action->execute($stage);
        } catch (DomainException $e) {
            return back()->withErrors(['seeding' => $e->getMessage()]);
        }

        return redirect()
            ->route('stages.show', [$league, $season, $stage])
            ->with('status', 'Bracket seeded from group results.');
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
     * Cross-group ranking of the teams finishing just below the automatic
     * qualification spots (position = advances-per-group + 1, so with the
     * default 2 qualifiers per group this is the third-placed table). Only
     * built when the stage opts in via config.best_placed_count — the number
     * of these teams that qualify for the next stage.
     *
     * @return null|array{position: int, qualify_count: int, rows: array<int, array<string, mixed>>}
     */
    private function buildBestPlaced(Stage $stage, BestPlacedCalculator $calculator): ?array
    {
        if (! $stage->format->hasGroups() || $stage->groups->isEmpty()) {
            return null;
        }

        $qualifyCount = (int) ($stage->config['best_placed_count'] ?? 0);

        if ($qualifyCount < 1) {
            return null;
        }

        $position = ($stage->advances_count ?? 2) + 1;

        return [
            'position' => $position,
            'qualify_count' => $qualifyCount,
            'rows' => $calculator->calculate($stage, $position)
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

        $entrants = $this->entrantSlots($stage);

        return $byRound
            ->map(fn ($games, $round) => [
                'round' => (int) $round,
                'label' => $this->roundLabel($games->count()),
                'games' => $games->values()->map(fn ($game) => [
                    'id' => $game->id,
                    'bracket_position' => $game->bracket_position,
                    'home_team' => $game->homeTeam?->only(['id', 'name', 'acronym']),
                    'away_team' => $game->awayTeam?->only(['id', 'name', 'acronym']),
                    'home_placeholder' => $this->slotPlaceholder($entrants, (int) $round, $game, 'home'),
                    'away_placeholder' => $this->slotPlaceholder($entrants, (int) $round, $game, 'away'),
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
     * The stage's entrant slot descriptors, or [] when unset/invalid — a
     * malformed config should degrade to plain TBD slots, not a 500.
     *
     * @return array<int, EntrantSlot>
     */
    private function entrantSlots(Stage $stage): array
    {
        try {
            return EntrantSlot::listForStage($stage);
        } catch (DomainException) {
            return [];
        }
    }

    /**
     * Descriptor label for an unfilled round-1 slot ("Winner Group A"),
     * null once a real team occupies it or for rounds the descriptors
     * don't cover.
     *
     * @param  array<int, EntrantSlot>  $entrants
     */
    private function slotPlaceholder(array $entrants, int $round, Game $game, string $side): ?string
    {
        if ($round !== 1 || $entrants === []) {
            return null;
        }

        if (($side === 'home' ? $game->home_team_id : $game->away_team_id) !== null) {
            return null;
        }

        $slot = $entrants[2 * $game->bracket_position + ($side === 'home' ? 0 : 1)] ?? null;

        return $slot?->label();
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
            'sourceStage' => $stage->format->isBracket() ? $this->buildSourceStage($stage) : null,
        ]);
    }

    /**
     * The grouped stage that feeds this knockout stage, shaped for the
     * entrant builder's "Winner Group A"-style slot options. Null when the
     * season has no earlier grouped stage.
     *
     * @return null|array{id: int, name: string, advances_count: int, best_placed_count: int, groups: array<int, array{id: int, name: string}>}
     */
    private function buildSourceStage(Stage $stage): ?array
    {
        $source = $stage->previousGroupedStage()?->load('groups:id,stage_id,name,order');

        if ($source === null) {
            return null;
        }

        return [
            'id' => $source->id,
            'name' => $source->name,
            'advances_count' => $source->advances_count ?? 2,
            'best_placed_count' => (int) ($source->config['best_placed_count'] ?? 0),
            'groups' => $source->groups
                ->map(fn ($group) => ['id' => $group->id, 'name' => $group->name])
                ->all(),
        ];
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
