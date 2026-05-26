<?php

use App\Domain\Standings\RoundRobinStandingsCalculator;
use App\Models\Game;
use App\Models\Group;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

/**
 * Helper: build a stage with N season-attached teams, eager-loaded the way
 * the controllers do at render time.
 *
 * @return array{0: Stage, 1: Collection<int, Team>}
 */
function ungroupedStage(int $teamCount): array
{
    $season = Season::factory()->create();
    $teams = Team::factory()->count($teamCount)->create();
    $season->teams()->attach($teams);

    $stage = Stage::factory()->create(['season_id' => $season->id]);

    return [$stage->fresh(['season.teams', 'games.result']), $teams];
}

/**
 * Persist a finished game with a score. Returns the Game model.
 */
function playedGame(Stage $stage, Team $home, Team $away, int $hs, int $as, ?string $date = null, ?int $groupId = null): Game
{
    $game = Game::factory()->create([
        'stage_id' => $stage->id,
        'season_id' => $stage->season_id,
        'group_id' => $groupId,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'match_date' => $date ?? now()->subDays(rand(1, 30)),
    ]);

    Result::factory()->create([
        'game_id' => $game->id,
        'home_team_score' => $hs,
        'away_team_score' => $as,
    ]);

    return $game;
}

describe('RoundRobinStandingsCalculator (ungrouped)', function () {
    it('returns a row per team with all zeros when no games have been played', function () {
        [$stage, $teams] = ungroupedStage(4);

        $rows = (new RoundRobinStandingsCalculator)->calculate($stage->fresh(['season.teams', 'games.result']));

        expect($rows)->toHaveCount(4);
        foreach ($rows as $row) {
            expect($row->played)->toBe(0);
            expect($row->points)->toBe(0);
            expect($row->goals_for)->toBe(0);
            expect($row->goals_against)->toBe(0);
            expect($row->goal_difference)->toBe(0);
            expect($row->form)->toBe('');
        }
    });

    it('credits a 2-1 win correctly (W=3, GF=2, GA=1)', function () {
        [$stage, $teams] = ungroupedStage(2);
        [$home, $away] = $teams;

        playedGame($stage, $home, $away, 2, 1);

        $rows = (new RoundRobinStandingsCalculator)
            ->calculate($stage->fresh(['season.teams', 'games.result']))
            ->keyBy('team_id');

        expect($rows[$home->id]->won)->toBe(1);
        expect($rows[$home->id]->points)->toBe(3);
        expect($rows[$home->id]->goals_for)->toBe(2);
        expect($rows[$home->id]->goals_against)->toBe(1);
        expect($rows[$home->id]->goal_difference)->toBe(1);

        expect($rows[$away->id]->lost)->toBe(1);
        expect($rows[$away->id]->points)->toBe(0);
        expect($rows[$away->id]->goals_for)->toBe(1);
        expect($rows[$away->id]->goals_against)->toBe(2);
        expect($rows[$away->id]->goal_difference)->toBe(-1);
    });

    it('credits a draw correctly (1 point each, equal GF/GA)', function () {
        [$stage, $teams] = ungroupedStage(2);
        [$home, $away] = $teams;

        playedGame($stage, $home, $away, 1, 1);

        $rows = (new RoundRobinStandingsCalculator)
            ->calculate($stage->fresh(['season.teams', 'games.result']))
            ->keyBy('team_id');

        expect($rows[$home->id]->drawn)->toBe(1);
        expect($rows[$home->id]->points)->toBe(1);
        expect($rows[$away->id]->drawn)->toBe(1);
        expect($rows[$away->id]->points)->toBe(1);
    });

    it('ignores games without a recorded result', function () {
        [$stage, $teams] = ungroupedStage(2);
        [$home, $away] = $teams;

        Game::factory()->create([
            'stage_id' => $stage->id,
            'season_id' => $stage->season_id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);

        $rows = (new RoundRobinStandingsCalculator)
            ->calculate($stage->fresh(['season.teams', 'games.result']));

        foreach ($rows as $row) {
            expect($row->played)->toBe(0);
        }
    });

    it('orders rows by points then goal difference then goals for then name', function () {
        [$stage, $teams] = ungroupedStage(4);
        [$a, $b, $c, $d] = $teams;

        // Set deterministic names so the alphabetical tiebreak is stable.
        $a->update(['name' => 'Alpha FC']);
        $b->update(['name' => 'Beta FC']);
        $c->update(['name' => 'Charlie FC']);
        $d->update(['name' => 'Delta FC']);

        // Alpha: W 3-0 vs Beta            → 3 pts, GD +3, GF 3
        // Beta:  W 2-0 vs Charlie          → 3 pts, GD +2, GF 2
        // Charlie: W 2-1 vs Delta          → 3 pts, GD +1, GF 2
        // Delta: ...all losses → 0 pts
        playedGame($stage, $a, $b, 3, 0);
        playedGame($stage, $b, $c, 2, 0);
        playedGame($stage, $c, $d, 2, 1);

        $rows = (new RoundRobinStandingsCalculator)
            ->calculate($stage->fresh(['season.teams', 'games.result']));

        // Three teams have 3 points each — tiebreaker order:
        //   1. points → tied at 3
        //   2. gd     → Alpha +3, Beta +2, Charlie +1   (Delta -2)
        //   3. ...    → unused, decided already
        expect($rows->pluck('team_name')->all())->toBe([
            'Alpha FC',
            'Beta FC',
            'Charlie FC',
            'Delta FC',
        ]);
    });

    it('respects custom points_per_win / points_per_draw from stage.config', function () {
        [$stage, $teams] = ungroupedStage(2);
        $stage->update(['config' => ['points_per_win' => 2, 'points_per_draw' => 0]]);
        [$home, $away] = $teams;

        playedGame($stage, $home, $away, 2, 1);

        $rows = (new RoundRobinStandingsCalculator)
            ->calculate($stage->fresh(['season.teams', 'games.result']))
            ->keyBy('team_id');

        expect($rows[$home->id]->points)->toBe(2);
        expect($rows[$away->id]->points)->toBe(0);
    });

    it('builds the form string from the last 5 games newest-first', function () {
        [$stage, $teams] = ungroupedStage(2);
        [$home, $away] = $teams;

        // Six games over six chronological days: W W D L W L (oldest → newest)
        // form should keep the last 5 newest-first: L W L D W
        playedGame($stage, $home, $away, 3, 0, '2026-04-01 00:00:00'); // W for home
        playedGame($stage, $home, $away, 1, 0, '2026-04-02 00:00:00'); // W for home
        playedGame($stage, $home, $away, 1, 1, '2026-04-03 00:00:00'); // D
        playedGame($stage, $home, $away, 0, 1, '2026-04-04 00:00:00'); // L for home
        playedGame($stage, $home, $away, 2, 1, '2026-04-05 00:00:00'); // W for home
        playedGame($stage, $home, $away, 0, 1, '2026-04-06 00:00:00'); // L for home

        $rows = (new RoundRobinStandingsCalculator)
            ->calculate($stage->fresh(['season.teams', 'games.result']))
            ->keyBy('team_id');

        expect($rows[$home->id]->form)->toBe('LWLDW');
    });
});

describe('RoundRobinStandingsCalculator (grouped)', function () {
    it('only counts games tagged with the requested group', function () {
        $season = Season::factory()->create();
        $stage = Stage::factory()->groupStage()->create(['season_id' => $season->id]);

        $groupA = Group::factory()->create(['stage_id' => $stage->id]);
        $teamsA = Team::factory()->count(2)->create();
        $season->teams()->attach($teamsA);
        $groupA->teams()->attach($teamsA);

        $groupB = Group::factory()->create(['stage_id' => $stage->id]);
        $teamsB = Team::factory()->count(2)->create();
        $season->teams()->attach($teamsB);
        $groupB->teams()->attach($teamsB);

        // A game in each group.
        playedGame($stage, $teamsA[0], $teamsA[1], 3, 0, '2026-04-01', $groupA->id);
        playedGame($stage, $teamsB[0], $teamsB[1], 1, 1, '2026-04-01', $groupB->id);

        $stage = $stage->fresh(['season.teams', 'games.result']);

        $rowsA = (new RoundRobinStandingsCalculator)->calculate($stage, $groupA->fresh('teams'));
        $rowsB = (new RoundRobinStandingsCalculator)->calculate($stage, $groupB->fresh('teams'));

        // Group A: a clear winner with 3 pts.
        expect($rowsA->first()->points)->toBe(3);
        expect($rowsA->first()->team_id)->toBe($teamsA[0]->id);

        // Group B: both teams drawn — both have 1 pt.
        foreach ($rowsB as $row) {
            expect($row->points)->toBe(1);
        }
    });
});
