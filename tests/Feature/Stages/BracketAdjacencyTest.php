<?php

use App\Actions\GenerateFixtures;
use App\Actions\SeedStageFromGroups;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Group;
use App\Models\League;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;

/**
 * Build 8 single-game groups (A–H) whose home side wins, plus a knockout
 * stage whose entrants are the eight group winners in A…H order. This is
 * the contract the entrant builder's projected-bracket view mirrors: the
 * entrant order is the bracket tree, so consecutive round-1 matches feed
 * each next-round game.
 *
 * @return array{0: Stage, 1: array<string, Team>}
 */
function bracketAdjacencyScaffold(): array
{
    $league = League::factory()->create();
    $season = Season::factory()->create(['league_id' => $league->id]);

    $groupStage = Stage::factory()->groupStage()->create([
        'season_id' => $season->id,
        'name' => 'Groups',
        'order' => 10,
        'advances_count' => 1,
    ]);

    $winners = [];
    $entrants = [];

    foreach (range('A', 'H') as $letter) {
        $group = Group::factory()->create(['stage_id' => $groupStage->id, 'name' => "Group {$letter}"]);
        $winner = Team::factory()->create(['name' => "Winner {$letter}"]);
        $loser = Team::factory()->create(['name' => "Loser {$letter}"]);

        $season->teams()->attach([$winner->id, $loser->id]);
        $group->teams()->attach([$winner->id, $loser->id]);

        $game = Game::factory()->create([
            'stage_id' => $groupStage->id,
            'season_id' => $season->id,
            'group_id' => $group->id,
            'home_team_id' => $winner->id,
            'away_team_id' => $loser->id,
            'status' => GameStatus::FullTime,
            'match_date' => now()->subDay(),
        ]);
        Result::factory()->create(['game_id' => $game->id, 'home_team_score' => 2, 'away_team_score' => 0]);

        $winners[$letter] = $winner;
        $entrants[] = ['type' => 'group', 'group' => "Group {$letter}", 'position' => 1];
    }

    $knockout = Stage::factory()->singleElimination()->create([
        'season_id' => $season->id,
        'name' => 'Knockout',
        'order' => 20,
        'config' => ['entrants' => $entrants],
    ]);

    app(GenerateFixtures::class)->execute($knockout->fresh('season.teams'));

    return [$knockout, $winners];
}

it('routes adjacent round-1 match winners into the same next-round game', function () {
    [$knockout, $w] = bracketAdjacencyScaffold();

    app(SeedStageFromGroups::class)->execute($knockout);

    // Decide every round-1 game for the home side — the earlier entrant slot,
    // i.e. the A/C/E/G group winners — and let advancement promote them.
    $knockout->games()->where('round', 1)->orderBy('bracket_position')->get()
        ->each(function (Game $game): void {
            Result::factory()->create(['game_id' => $game->id, 'home_team_score' => 1, 'away_team_score' => 0]);
            $game->update(['status' => GameStatus::FullTime]);
        });

    $round2 = $knockout->games()->where('round', 2)->orderBy('bracket_position')->get();

    // Matches 1 & 2 (Winner A v Winner B, Winner C v Winner D) feed the first
    // next-round game; matches 3 & 4 feed the second. So the winners set up to
    // meet are decided purely by the order the matches sit in.
    expect($round2[0]->home_team_id)->toBe($w['A']->id);
    expect($round2[0]->away_team_id)->toBe($w['C']->id);
    expect($round2[1]->home_team_id)->toBe($w['E']->id);
    expect($round2[1]->away_team_id)->toBe($w['G']->id);
});

it('changes who meets next round when the match order changes', function () {
    [$knockout, $w] = bracketAdjacencyScaffold();

    // Swap matches 2 and 3 (bracket positions 1 and 2): now Winner C/D share
    // the first next-round game's far side with Winner E/F instead.
    $entrants = $knockout->config['entrants'];
    [$entrants[2], $entrants[3], $entrants[4], $entrants[5]] = [$entrants[4], $entrants[5], $entrants[2], $entrants[3]];
    $knockout->update(['config' => ['entrants' => $entrants]]);

    app(SeedStageFromGroups::class)->execute($knockout->fresh());

    $knockout->games()->where('round', 1)->orderBy('bracket_position')->get()
        ->each(function (Game $game): void {
            Result::factory()->create(['game_id' => $game->id, 'home_team_score' => 1, 'away_team_score' => 0]);
            $game->update(['status' => GameStatus::FullTime]);
        });

    $round2 = $knockout->games()->where('round', 2)->orderBy('bracket_position')->get();

    // Winner A now meets Winner E (match 2 became E v F), confirming the order
    // drives the pairing.
    expect($round2[0]->home_team_id)->toBe($w['A']->id);
    expect($round2[0]->away_team_id)->toBe($w['E']->id);
});
