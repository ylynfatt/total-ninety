<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\League;
use App\Models\Player;
use App\Models\Season;
use App\Models\Stage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public gamecast for a single fixture: the live score plus a chronological
 * timeline of events (goals, cards, subs, commentary).
 *
 * The page subscribes to the `game.{id}` channel — ScoreUpdated patches the
 * scoreline in place, while GameEventRecorded triggers a scoped reload of the
 * `events` prop (the broadcast payload carries only IDs, so we re-resolve the
 * names server-side rather than duplicating a lookup table on the client).
 */
class GamecastController extends Controller
{
    public function show(League $league, Season $season, Stage $stage, Game $game): Response
    {
        $this->ensureChain($league, $season, $stage, $game);

        $game->load([
            'homeTeam:id,name,acronym',
            'awayTeam:id,name,acronym',
            'result',
            'events.team:id,acronym',
            'events.player:id,first_name,last_name',
            'events.assistPlayer:id,first_name,last_name',
            'events.secondaryPlayer:id,first_name,last_name',
        ]);

        return Inertia::render('Gamecast/Show', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season->only(['id', 'name']),
            'stage' => $stage->only(['id', 'name']),
            'game' => [
                'id' => $game->id,
                'status' => $game->status->value,
                'status_label' => $game->status->label(),
                'current_minute' => $game->current_minute,
                'match_date' => $game->match_date,
                'location' => $game->location,
                'home_team' => $game->homeTeam?->only(['id', 'name', 'acronym']),
                'away_team' => $game->awayTeam?->only(['id', 'name', 'acronym']),
                'home_team_score' => $game->result?->home_team_score,
                'away_team_score' => $game->result?->away_team_score,
            ],
            'events' => $game->events->map(fn (GameEvent $event): array => $this->transformEvent($event))->all(),
        ]);
    }

    /**
     * Flatten an event with its resolved team/player names for the timeline.
     *
     * @return array<string, mixed>
     */
    private function transformEvent(GameEvent $event): array
    {
        return [
            'id' => $event->id,
            'minute' => $event->minute,
            'stoppage' => $event->stoppage,
            'type' => $event->type->value,
            'type_label' => $event->type->label(),
            'is_scoring' => $event->type->isScoringEvent(),
            'team_acronym' => $event->team?->acronym,
            'player_name' => $this->playerName($event->player),
            'assist_player_name' => $this->playerName($event->assistPlayer),
            'secondary_player_name' => $this->playerName($event->secondaryPlayer),
            'description' => $event->description,
        ];
    }

    private function playerName(?Player $player): ?string
    {
        if ($player === null) {
            return null;
        }

        return trim("{$player->first_name} {$player->last_name}");
    }

    /**
     * Walk the parent chain so a mistargeted URL 404s rather than leaking data.
     */
    private function ensureChain(League $league, Season $season, Stage $stage, Game $game): void
    {
        abort_if($season->league_id !== $league->id, 404);
        abort_if($stage->season_id !== $season->id, 404);
        abort_if($game->stage_id !== $stage->id, 404);
    }
}
