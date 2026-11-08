<?php

namespace App\Actions;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\League;
use App\Models\Season;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildDashboardOverview
{
    /** @return array<string, mixed> */
    public function execute(User $user): array
    {
        $today = today();
        $tomorrow = $today->copy()->addDay();
        $ownerId = $user->id;
        $games = Game::query()
            ->whereHas('stage.season.league', fn (Builder $query) => $query->where('user_id', $ownerId));
        $inProgress = [GameStatus::Live, GameStatus::HalfTime];

        $matchday = (clone $games)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('match_date', '>=', $today)->where('match_date', '<', $tomorrow))
                ->orWhereIn('status', $inProgress))
            ->with($this->gameRelations())
            ->orderBy('match_date')
            ->orderBy('id')
            ->get();

        $activeGames = (clone $games)->whereHas('stage.season', fn (Builder $query) => $query->where('is_active', true));
        $overdue = (clone $activeGames)
            ->where('status', GameStatus::Scheduled)
            ->where('match_date', '<', $today)
            ->whereDoesntHave('result');
        $unscheduled = (clone $activeGames)
            ->where('status', GameStatus::Scheduled)
            ->whereNull('match_date')
            ->whereHas('homeTeam')
            ->whereHas('awayTeam');
        $unpreparedStages = Stage::query()
            ->whereHas('season', fn (Builder $query) => $query->where('is_active', true)
                ->whereHas('league', fn (Builder $query) => $query->where('user_id', $ownerId)))
            ->whereDoesntHave('games');

        $attention = [
            $this->gameAttention($overdue, 'overdue', 'Check overdue matches', 'Scheduled before today with no result recorded.', 'Review match'),
            $this->gameAttention($unscheduled, 'unscheduled', 'Schedule fixtures', 'Teams are assigned, but a kickoff date is still needed.', 'Set kickoff'),
            [
                'key' => 'stages',
                'title' => 'Set up stage fixtures',
                'description' => 'Active-season stages without generated fixtures. Review teams and groups first.',
                'count' => (clone $unpreparedStages)->count(),
                'items' => $unpreparedStages->with('season.league')->orderBy('id')->limit(5)->get()
                    ->map(fn (Stage $stage): array => [
                        'id' => $stage->id,
                        'title' => $stage->name,
                        'context' => $stage->season->league->name.' · '.$stage->season->name,
                        'href' => route('stages.show', [$stage->season->league, $stage->season, $stage]),
                        'action' => 'Review stage',
                    ]),
            ],
        ];

        $leagues = League::query()->where('user_id', $ownerId)
            ->with(['seasons' => fn (HasMany $query) => $query
                ->where('is_active', true)
                ->withCount(['games', 'games as completed_games_count' => fn (Builder $query) => $query->where('status', GameStatus::FullTime)])
                ->with(['games' => fn (HasMany $query) => $query
                    ->where('status', GameStatus::Scheduled)->where('match_date', '>=', now())
                    ->with(['homeTeam', 'awayTeam'])->orderBy('match_date')->orderBy('id')->limit(1)])
                ->orderByDesc('starts_on')->orderBy('id')])
            ->orderBy('name')->orderBy('id')->get();

        return [
            'summary' => [
                'live' => $matchday->filter(fn (Game $game): bool => $game->status->isInProgress())->count(),
                'today' => $matchday->filter(fn (Game $game): bool => $game->match_date !== null
                    && $game->match_date->greaterThanOrEqualTo($today)
                    && $game->match_date->lessThan($tomorrow))->count(),
                'attention' => array_sum(array_column($attention, 'count')),
            ],
            'matchday' => [
                'live' => $matchday->filter(fn (Game $game): bool => $game->status->isInProgress())->values()->map($this->transformGame(...)),
                'upcoming' => $matchday->where('status', GameStatus::Scheduled)->values()->map($this->transformGame(...)),
                'finished' => $matchday->where('status', GameStatus::FullTime)->values()->map($this->transformGame(...)),
                'other' => $matchday->whereIn('status', [GameStatus::Postponed, GameStatus::Cancelled])->values()->map($this->transformGame(...)),
            ],
            'attention' => $attention,
            'leagues' => $leagues->map(fn (League $league): array => [
                'id' => $league->id,
                'name' => $league->name,
                'isPublic' => $league->is_public,
                'href' => route('leagues.show', $league),
                'createSeasonUrl' => route('seasons.create', $league),
                'seasons' => $league->seasons->map(fn (Season $season): array => [
                    'id' => $season->id,
                    'name' => $season->name,
                    'href' => route('seasons.show', [$league, $season]),
                    'games' => $season->games_count,
                    'completed' => $season->completed_games_count,
                    'nextFixture' => $season->games->first() ? [
                        'homeTeam' => $season->games->first()->homeTeam?->name ?? 'TBD',
                        'awayTeam' => $season->games->first()->awayTeam?->name ?? 'TBD',
                        'kickoff' => $season->games->first()->match_date->toISOString(),
                    ] : null,
                ]),
            ]),
        ];
    }

    /** @return list<string> */
    private function gameRelations(): array
    {
        return ['homeTeam', 'awayTeam', 'result', 'stage.season.league'];
    }

    /** @return array<string, mixed> */
    private function transformGame(Game $game): array
    {
        $stage = $game->stage;
        $season = $stage->season;
        $league = $season->league;
        $parameters = [$league, $season, $stage, $game];

        return [
            'id' => $game->id,
            'homeTeam' => $game->homeTeam?->name ?? 'TBD',
            'awayTeam' => $game->awayTeam?->name ?? 'TBD',
            'homeScore' => $game->result?->home_team_score,
            'awayScore' => $game->result?->away_team_score,
            'status' => $game->status->value,
            'statusLabel' => $game->status->label(),
            'kickoff' => $game->match_date?->toISOString(),
            'location' => $game->location,
            'context' => $league->name.' · '.$season->name.' · '.$stage->name,
            'viewUrl' => route('games.show', $parameters),
            'manageUrl' => route('fixtures.edit', $parameters),
        ];
    }

    /** @return array<string, mixed> */
    private function gameAttention(Builder $query, string $key, string $title, string $description, string $action): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'count' => (clone $query)->count(),
            'items' => $query->with($this->gameRelations())->orderBy('match_date')->orderBy('id')->limit(5)->get()
                ->map(function (Game $game) use ($action): array {
                    $match = $this->transformGame($game);

                    return [
                        'id' => $game->id,
                        'title' => $match['homeTeam'].' vs '.$match['awayTeam'],
                        'context' => $match['context'],
                        'href' => $match['manageUrl'],
                        'action' => $action,
                    ];
                }),
        ];
    }
}
