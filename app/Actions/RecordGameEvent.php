<?php

namespace App\Actions;

use App\Enums\GameEventType;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Result;
use Illuminate\Support\Facades\DB;

/**
 * Record a single timeline event on a game and, for scoring events, keep the
 * Result scoreline in step.
 *
 * Scoring rule: `team_id` is always the team of the player involved, so a
 * regular Goal / PenaltyGoal credits that team while an OwnGoal credits the
 * opponent. The whole thing runs in a transaction so a goal never lands on the
 * timeline without its score (or vice versa).
 *
 * The separate manual Result editor remains the path for historical or
 * correction entry — this action only fires from the live gamecast editor.
 */
class RecordGameEvent
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Game $game, array $attributes): GameEvent
    {
        return DB::transaction(function () use ($game, $attributes): GameEvent {
            $event = $game->events()->create($attributes);

            if ($event->type->isScoringEvent()) {
                $this->applyGoal($game, $event);
            }

            return $event;
        });
    }

    private function applyGoal(Game $game, GameEvent $event): void
    {
        $creditedTeamId = $event->type === GameEventType::OwnGoal
            ? $this->opponentTeamId($game, $event->team_id)
            : $event->team_id;

        if ($creditedTeamId === null) {
            return;
        }

        $result = Result::firstOrCreate(
            ['game_id' => $game->id],
            ['home_team_score' => 0, 'away_team_score' => 0],
        );

        if ($creditedTeamId === $game->home_team_id) {
            $result->increment('home_team_score');
        } elseif ($creditedTeamId === $game->away_team_id) {
            $result->increment('away_team_score');
        }
    }

    private function opponentTeamId(Game $game, ?int $teamId): ?int
    {
        if ($teamId === $game->home_team_id) {
            return $game->away_team_id;
        }

        if ($teamId === $game->away_team_id) {
            return $game->home_team_id;
        }

        return null;
    }
}
