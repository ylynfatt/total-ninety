<?php

use App\Actions\GenerateFixtures;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;

/**
 * Build a 4-team single-elimination bracket: round 1 has positions 0 and 1,
 * the final (round 2) has position 0. Returns [stage, round1, final].
 */
function fourTeamBracket(): array
{
    $season = Season::factory()->create();
    $season->teams()->attach(Team::factory()->count(4)->create());
    $stage = Stage::factory()->singleElimination()->create(['season_id' => $season->id]);

    app(GenerateFixtures::class)->execute($stage);

    $round1 = Game::where('stage_id', $stage->id)->where('round', 1)->orderBy('bracket_position')->get();
    $final = Game::where('stage_id', $stage->id)->where('round', 2)->where('bracket_position', 0)->first();

    return [$stage, $round1, $final];
}

function finalize(Game $game, int $home, int $away): void
{
    Result::create(['game_id' => $game->id, 'home_team_score' => $home, 'away_team_score' => $away]);
    $game->update(['status' => GameStatus::FullTime]);
}

it('advances the round-1 position-0 winner into the final home slot', function () {
    [, $round1, $final] = fourTeamBracket();
    $game = $round1[0];

    finalize($game, 2, 0); // home wins

    expect($final->fresh()->home_team_id)->toBe($game->home_team_id);
});

it('advances the position-1 winner into the final away slot', function () {
    [, $round1, $final] = fourTeamBracket();
    $game = $round1[1];

    finalize($game, 0, 3); // away wins

    expect($final->fresh()->away_team_id)->toBe($game->away_team_id);
});

it('fills both finalists once both semifinals are decided', function () {
    [, $round1, $final] = fourTeamBracket();

    finalize($round1[0], 1, 0); // home of game 0
    finalize($round1[1], 0, 2); // away of game 1

    $final->refresh();
    expect($final->home_team_id)->toBe($round1[0]->home_team_id)
        ->and($final->away_team_id)->toBe($round1[1]->away_team_id);
});

it('does not advance on a draw', function () {
    [, $round1, $final] = fourTeamBracket();

    finalize($round1[0], 1, 1);

    expect($final->fresh()->home_team_id)->toBeNull();
});

it('does not advance before the game is final', function () {
    [, $round1, $final] = fourTeamBracket();
    $game = $round1[0];

    Result::create(['game_id' => $game->id, 'home_team_score' => 3, 'away_team_score' => 1]);
    $game->update(['status' => GameStatus::Live]);

    expect($final->fresh()->home_team_id)->toBeNull();
});

it('re-promotes the corrected winner when a result is amended', function () {
    [, $round1, $final] = fourTeamBracket();
    $game = $round1[0];

    finalize($game, 2, 0); // home wins → advances home
    expect($final->fresh()->home_team_id)->toBe($game->home_team_id);

    // Correct the result so the away side actually won.
    $game->result->update(['home_team_score' => 0, 'away_team_score' => 2]);

    expect($final->fresh()->home_team_id)->toBe($game->away_team_id);
});

it('no-ops on the final itself (no next round to fill)', function () {
    [, , $final] = fourTeamBracket();
    $final->update(['home_team_id' => Team::factory()->create()->id, 'away_team_id' => Team::factory()->create()->id]);

    finalize($final, 3, 1);

    // Nothing to assert beyond "it didn't throw" — the final has no parent slot.
    expect(true)->toBeTrue();
});

it('ignores non-bracket games', function () {
    $game = Game::factory()->create(['round' => null, 'bracket_position' => null]);

    finalize($game, 2, 1);

    expect(Game::where('round', 2)->whereNotNull('home_team_id')->where('id', '!=', $game->id)->exists())->toBeFalse();
});
