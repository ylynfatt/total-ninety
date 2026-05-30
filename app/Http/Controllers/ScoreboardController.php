<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use Inertia\Inertia;
use Inertia\Response;

class ScoreboardController extends Controller
{
    /**
     * Public live scoreboard — every game currently in progress across all
     * leagues. The Vue page subscribes to the `scoreboard.live` channel and
     * patches this list in real time as scores and statuses change.
     */
    public function index(): Response
    {
        $games = Game::query()
            ->whereIn('status', [GameStatus::Live, GameStatus::HalfTime])
            ->with(['homeTeam:id,name,acronym', 'awayTeam:id,name,acronym', 'result', 'season.league:id,name,slug'])
            ->orderBy('match_date')
            ->get()
            ->map(fn (Game $game): array => $this->transform($game))
            ->all();

        return Inertia::render('Scoreboard/Index', [
            'games' => $games,
        ]);
    }

    /**
     * Shape a game into the flat payload the scoreboard renders. Matches the
     * fields broadcast by ScoreUpdated / GameStatusChanged so the client can
     * patch entries in place.
     *
     * @return array<string, mixed>
     */
    private function transform(Game $game): array
    {
        return [
            'id' => $game->id,
            'home_team' => [
                'name' => $game->homeTeam?->name,
                'acronym' => $game->homeTeam?->acronym,
            ],
            'away_team' => [
                'name' => $game->awayTeam?->name,
                'acronym' => $game->awayTeam?->acronym,
            ],
            'home_team_score' => $game->result?->home_team_score,
            'away_team_score' => $game->result?->away_team_score,
            'status' => $game->status->value,
            'status_label' => $game->status->label(),
            'current_minute' => $game->current_minute,
            'league_name' => $game->season?->league?->name,
        ];
    }
}
