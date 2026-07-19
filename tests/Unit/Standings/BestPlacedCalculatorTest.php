<?php

use App\Domain\Standings\BestPlacedCalculator;
use App\Models\Game;
use App\Models\Group;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;

/**
 * Build a GroupStage with named groups of named teams, e.g.
 * ['Group A' => ['Ajax', 'Barca', 'Celtic'], 'Group B' => [...]].
 * Returns [stage, teams keyed by name, groups keyed by name].
 *
 * @param  array<string, array<int, string>>  $groupMap
 * @return array{0: Stage, 1: array<string, Team>, 2: array<string, Group>}
 */
function bestPlacedScaffold(array $groupMap): array
{
    $season = Season::factory()->create();
    $stage = Stage::factory()->groupStage()->create(['season_id' => $season->id]);

    $teams = [];
    $groups = [];

    foreach ($groupMap as $groupName => $teamNames) {
        $group = Group::factory()->create(['stage_id' => $stage->id, 'name' => $groupName]);
        $groups[$groupName] = $group;

        foreach ($teamNames as $teamName) {
            $team = Team::factory()->create(['name' => $teamName]);
            $season->teams()->attach($team);
            $group->teams()->attach($team);
            $teams[$teamName] = $team;
        }
    }

    return [$stage, $teams, $groups];
}

/**
 * Persist a decided group game between two named teams.
 */
function decideGroupGame(Stage $stage, Group $group, Team $home, Team $away, int $hs, int $as): void
{
    $game = Game::factory()->create([
        'stage_id' => $stage->id,
        'season_id' => $stage->season_id,
        'group_id' => $group->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'match_date' => now()->subDay(),
    ]);

    Result::factory()->create([
        'game_id' => $game->id,
        'home_team_score' => $hs,
        'away_team_score' => $as,
    ]);
}

function freshForBestPlaced(Stage $stage): Stage
{
    return $stage->fresh(['groups.teams', 'games.result', 'season.teams']);
}

describe('BestPlacedCalculator', function () {
    it('ranks third-placed teams across groups by points, then goal difference, then goals for', function () {
        [$stage, $t, $g] = bestPlacedScaffold([
            'Group A' => ['A1', 'A2', 'A3'],
            'Group B' => ['B1', 'B2', 'B3'],
            'Group C' => ['C1', 'C2', 'C3'],
        ]);

        // Group A: A3 finishes 3rd with 3 pts, GD −4 (h2h vs A3 puts A2 2nd).
        decideGroupGame($stage, $g['Group A'], $t['A1'], $t['A2'], 1, 0);
        decideGroupGame($stage, $g['Group A'], $t['A1'], $t['A3'], 3, 0);
        decideGroupGame($stage, $g['Group A'], $t['A3'], $t['A2'], 2, 0);
        decideGroupGame($stage, $g['Group A'], $t['A2'], $t['A3'], 3, 0);

        // Group B: B3 finishes 3rd with 0 pts.
        decideGroupGame($stage, $g['Group B'], $t['B1'], $t['B3'], 2, 0);
        decideGroupGame($stage, $g['Group B'], $t['B2'], $t['B3'], 1, 0);
        decideGroupGame($stage, $g['Group B'], $t['B1'], $t['B2'], 1, 0);

        // Group C: C3 finishes 3rd with 3 pts, GD −2 (h2h vs C3 puts C2 2nd)
        // — better GD than A3, so C3 outranks A3 across groups.
        decideGroupGame($stage, $g['Group C'], $t['C1'], $t['C2'], 1, 0);
        decideGroupGame($stage, $g['Group C'], $t['C1'], $t['C3'], 1, 0);
        decideGroupGame($stage, $g['Group C'], $t['C3'], $t['C2'], 1, 0);
        decideGroupGame($stage, $g['Group C'], $t['C2'], $t['C3'], 2, 0);

        $ranked = app(BestPlacedCalculator::class)->calculate(freshForBestPlaced($stage), 3);

        expect($ranked)->toHaveCount(3);
        expect($ranked[0]->row->team_name)->toBe('C3'); // 3 pts, GD −2
        expect($ranked[0]->group_name)->toBe('Group C');
        expect($ranked[1]->row->team_name)->toBe('A3'); // 3 pts, GD −4
        expect($ranked[2]->row->team_name)->toBe('B3'); // 0 pts
    });

    it('skips groups that have no team at the requested position', function () {
        [$stage, $t, $g] = bestPlacedScaffold([
            'Group A' => ['A1', 'A2', 'A3'],
            'Group B' => ['B1', 'B2'], // only two teams — no 3rd place
        ]);

        $ranked = app(BestPlacedCalculator::class)->calculate(freshForBestPlaced($stage), 3);

        expect($ranked)->toHaveCount(1);
        expect($ranked[0]->group_name)->toBe('Group A');
    });

    it('breaks full ties alphabetically for a stable order', function () {
        // Group tables share the name tiebreak, so with no games played each
        // group's 3rd place is its alphabetically-last team.
        [$stage, $t, $g] = bestPlacedScaffold([
            'Group A' => ['Zebra FC', 'A1', 'A2'],
            'Group B' => ['Bete FC', 'B1', 'B2'],
        ]);

        $ranked = app(BestPlacedCalculator::class)->calculate(freshForBestPlaced($stage), 3);

        expect($ranked->map(fn ($r) => $r->row->team_name)->all())
            ->toBe(['Bete FC', 'Zebra FC']);
    });

    it('tags each row with the group it came from and serializes both', function () {
        [$stage, $t, $g] = bestPlacedScaffold([
            'Group A' => ['A1', 'A2', 'A3'],
        ]);

        $ranked = app(BestPlacedCalculator::class)->calculate(freshForBestPlaced($stage), 3);

        expect($ranked[0]->group_id)->toBe($g['Group A']->id);
        expect($ranked[0]->toArray())
            ->toHaveKeys(['group_id', 'group_name', 'team_id', 'team_name', 'points', 'goal_difference']);
    });

    it('rejects ungrouped formats', function () {
        $season = Season::factory()->create();
        $stage = Stage::factory()->create(['season_id' => $season->id]);

        app(BestPlacedCalculator::class)->calculate($stage, 3);
    })->throws(DomainException::class);

    it('rejects positions below 1', function () {
        [$stage] = bestPlacedScaffold(['Group A' => ['A1', 'A2']]);

        app(BestPlacedCalculator::class)->calculate(freshForBestPlaced($stage), 0);
    })->throws(DomainException::class);
});
