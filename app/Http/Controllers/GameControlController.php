<?php

namespace App\Http\Controllers;

use App\Actions\RecordGameEvent;
use App\Http\Requests\StoreGameEventRequest;
use App\Http\Requests\UpdateGameStatusRequest;
use App\Models\Game;
use App\Models\League;
use App\Models\Season;
use App\Models\Stage;
use Illuminate\Http\RedirectResponse;

/**
 * Owner-only live controls for the gamecast: drive a game's lifecycle status
 * and record timeline events. Both flows broadcast (via the model observers)
 * so the public scoreboard / gamecast update in real time.
 */
class GameControlController extends Controller
{
    public function updateStatus(UpdateGameStatusRequest $request, League $league, Season $season, Stage $stage, Game $game): RedirectResponse
    {
        $this->ensureChain($league, $season, $stage, $game);

        $game->update($request->validated());

        return redirect()
            ->route('games.show', [$league, $season, $stage, $game])
            ->with('status', 'Game status updated.');
    }

    public function storeEvent(StoreGameEventRequest $request, RecordGameEvent $action, League $league, Season $season, Stage $stage, Game $game): RedirectResponse
    {
        $this->ensureChain($league, $season, $stage, $game);

        $action->execute($game, $request->validated());

        return redirect()
            ->route('games.show', [$league, $season, $stage, $game])
            ->with('status', 'Event recorded.');
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
