<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\League;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;

/**
 * Build league + season + stage + one fixture at the given status.
 * Returns [league, season, stage, game].
 *
 * @return array{0: League, 1: Season, 2: Stage, 3: Game}
 */
function fixtureAtStatus(GameStatus $status, ?Stage $stage = null): array
{
    $league = $stage?->season->league ?? League::factory()->create();
    $season = $stage?->season ?? Season::factory()->create(['league_id' => $league->id]);
    $stage ??= Stage::factory()->create(['season_id' => $season->id]);

    $game = Game::factory()->create([
        'stage_id' => $stage->id,
        'season_id' => $season->id,
        'home_team_id' => Team::factory()->create()->id,
        'away_team_id' => Team::factory()->create()->id,
        'status' => $status,
    ]);

    return [$league, $season, $stage, $game];
}

function postResult(League $league, Season $season, Stage $stage, Game $game, int $hs = 2, int $as = 1)
{
    return test()->actingAs($league->owner)
        ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/result", [
            'home_team_score' => $hs,
            'away_team_score' => $as,
        ]);
}

describe('Manual result entry marks unplayed games Full Time', function () {
    it('promotes a scheduled game to full time when its result is recorded', function () {
        [$league, $season, $stage, $game] = fixtureAtStatus(GameStatus::Scheduled);

        postResult($league, $season, $stage, $game)->assertRedirect();

        expect($game->fresh()->status)->toBe(GameStatus::FullTime);
    });

    it('promotes a postponed game to full time when its result is recorded', function () {
        [$league, $season, $stage, $game] = fixtureAtStatus(GameStatus::Postponed);

        postResult($league, $season, $stage, $game)->assertRedirect();

        expect($game->fresh()->status)->toBe(GameStatus::FullTime);
    });

    it('leaves an in-progress game untouched when a correction is entered', function (GameStatus $status) {
        [$league, $season, $stage, $game] = fixtureAtStatus($status);

        postResult($league, $season, $stage, $game)->assertRedirect();

        expect($game->fresh()->status)->toBe($status);
    })->with([
        'live' => GameStatus::Live,
        'half time' => GameStatus::HalfTime,
        'cancelled' => GameStatus::Cancelled,
    ]);

    it('advances the bracket winner when a knockout result is entered manually', function () {
        $season = Season::factory()->create();
        $stage = Stage::factory()->singleElimination()->create(['season_id' => $season->id]);

        [$league, , , $semi] = fixtureAtStatus(GameStatus::Scheduled, $stage);
        $semi->update(['round' => 1, 'bracket_position' => 0]);

        $final = Game::factory()->create([
            'stage_id' => $stage->id,
            'season_id' => $season->id,
            'home_team_id' => null,
            'away_team_id' => null,
            'round' => 2,
            'bracket_position' => 0,
        ]);

        postResult($league, $season, $stage, $semi->fresh(), 3, 1)->assertRedirect();

        expect($final->fresh()->home_team_id)->toBe($semi->home_team_id);
    });
});

describe('Backfill migration', function () {
    it('marks scheduled games that already have results as full time, leaving others alone', function () {
        [, , $stage, $withResult] = fixtureAtStatus(GameStatus::Scheduled);
        [, , , $withoutResult] = fixtureAtStatus(GameStatus::Scheduled, $stage);
        [, , , $live] = fixtureAtStatus(GameStatus::Live, $stage);

        Result::factory()->create(['game_id' => $withResult->id, 'home_team_score' => 1, 'away_team_score' => 0]);
        Result::factory()->create(['game_id' => $live->id, 'home_team_score' => 1, 'away_team_score' => 0]);

        (require database_path('migrations/2026_07_19_171436_backfill_full_time_status_for_games_with_results.php'))->up();

        expect($withResult->fresh()->status)->toBe(GameStatus::FullTime);
        expect($withoutResult->fresh()->status)->toBe(GameStatus::Scheduled);
        expect($live->fresh()->status)->toBe(GameStatus::Live);
    });
});
