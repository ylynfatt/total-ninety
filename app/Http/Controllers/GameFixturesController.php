<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Http\Requests\StoreResultRequest;
use App\Http\Requests\UpdateGameScheduleRequest;
use App\Models\Game;
use App\Models\League;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage a single Game (fixture) within a stage.
 *
 * Three flows on top of a generated fixture:
 *   1. Schedule        — set match_date + location
 *   2. Record a result — store home/away scores in the Result table
 *   3. Clear a result  — wipe the Result row for a game (e.g. a played-game
 *                        entry that needs to be undone)
 *
 * Games are created exclusively via the GenerateFixtures action — there is
 * no ad-hoc game-create UI in the Inertia tree. If we ever need that, it'd
 * be a separate, owner-gated flow.
 */
class GameFixturesController extends Controller
{
    public function edit(League $league, Season $season, Stage $stage, Game $game): Response
    {
        $this->ensureChain($league, $season, $stage, $game);
        $this->authorize('update', $game);

        $game->load(['homeTeam:id,name,acronym', 'awayTeam:id,name,acronym', 'result', 'group:id,name']);

        return Inertia::render('Games/Edit', [
            'league' => $league->only(['id', 'name', 'slug']),
            'season' => $season->only(['id', 'name']),
            'stage' => $stage->only(['id', 'name', 'format']),
            'game' => $game,
        ]);
    }

    public function updateSchedule(UpdateGameScheduleRequest $request, League $league, Season $season, Stage $stage, Game $game): RedirectResponse
    {
        $this->ensureChain($league, $season, $stage, $game);

        $game->update($request->validated());

        // Redirect to Stage Show rather than back() so the user sees their
        // change confirmed on the fixture list. back() would return to the
        // edit page, and if the user then used the browser back button, they
        // would land on a stale (history-cached) Stage Show that still
        // shows the old "TBD" date.
        return redirect()
            ->route('stages.show', [$league, $season, $stage])
            ->with('status', 'Schedule updated.');
    }

    public function storeResult(StoreResultRequest $request, League $league, Season $season, Stage $stage, Game $game): RedirectResponse
    {
        $this->ensureChain($league, $season, $stage, $game);

        // Entering a final score on a game that never went through the live
        // lifecycle is a declaration that the game was played. Marking it
        // Full Time (before the result lands, so observers see a final game)
        // keeps manual entry consistent with the gamecast path: bracket
        // winners advance and stage-completeness checks see the game as done.
        // In-progress games are left alone — the editor is a correction tool
        // there, not a way to end the match.
        if (in_array($game->status, [GameStatus::Scheduled, GameStatus::Postponed], true)) {
            $game->update(['status' => GameStatus::FullTime]);
        }

        // updateOrCreate handles both first-time entry and edits, since each
        // game has a unique constraint on game_id in the results table.
        Result::updateOrCreate(
            ['game_id' => $game->id],
            $request->validated(),
        );

        return redirect()
            ->route('stages.show', [$league, $season, $stage])
            ->with('status', 'Result recorded.');
    }

    public function destroyResult(League $league, Season $season, Stage $stage, Game $game): RedirectResponse
    {
        $this->ensureChain($league, $season, $stage, $game);
        $this->authorize('update', $game);

        $game->result()->delete();

        return redirect()
            ->route('stages.show', [$league, $season, $stage])
            ->with('status', 'Result cleared.');
    }

    /**
     * Walk the full parent chain so a mistargeted URL 404s instead of leaking
     * any data. scopeBindings() on the route already handles most of this,
     * but the defensive check survives a future route definition mistake.
     */
    private function ensureChain(League $league, Season $season, Stage $stage, Game $game): void
    {
        abort_if($season->league_id !== $league->id, 404);
        abort_if($stage->season_id !== $season->id, 404);
        abort_if($game->stage_id !== $stage->id, 404);
    }
}
