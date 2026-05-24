<?php

use App\Domain\Formats\RoundRobinDoubleGenerator;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;

/**
 * Helper duplicated here so this file is self-contained.
 */
function stageWithSeasonTeamsForDouble(int $teamCount): Stage
{
    $season = Season::factory()->create();
    $season->teams()->attach(Team::factory()->count($teamCount)->create());

    return Stage::factory()->create(['season_id' => $season->id]);
}

describe('RoundRobinDoubleGenerator', function () {
    it('produces n(n-1) fixtures for n teams', function (int $n) {
        $stage = stageWithSeasonTeamsForDouble($n);

        $pairs = (new RoundRobinDoubleGenerator)->generate($stage);

        expect($pairs)->toHaveCount($n * ($n - 1));
    })->with([
        'two teams' => 2,
        'three teams' => 3,
        'four teams' => 4,
        'eight teams' => 8,
        'twenty teams (premier league size)' => 20,
    ]);

    it('produces each unordered pair exactly twice', function () {
        $stage = stageWithSeasonTeamsForDouble(5);

        $pairs = (new RoundRobinDoubleGenerator)->generate($stage);

        $counts = $pairs->countBy(function (array $pair): string {
            $sorted = [$pair['home_team_id'], $pair['away_team_id']];
            sort($sorted);

            return implode('-', $sorted);
        });

        foreach ($counts as $count) {
            expect($count)->toBe(2);
        }
    });

    it('produces each ordered pair (home,away) exactly once', function () {
        $stage = stageWithSeasonTeamsForDouble(4);

        $pairs = (new RoundRobinDoubleGenerator)->generate($stage);

        $ordered = $pairs->map(fn (array $pair) => $pair['home_team_id'].':'.$pair['away_team_id']);

        expect($ordered->unique()->count())->toBe($pairs->count());
    });

    it('returns null group_id on every pair', function () {
        $stage = stageWithSeasonTeamsForDouble(4);

        $pairs = (new RoundRobinDoubleGenerator)->generate($stage);

        foreach ($pairs as $pair) {
            expect($pair['group_id'])->toBeNull();
        }
    });

    it('returns an empty collection for fewer than 2 teams', function () {
        $generator = new RoundRobinDoubleGenerator;

        expect($generator->generate(stageWithSeasonTeamsForDouble(0))->all())->toBe([]);
        expect($generator->generate(stageWithSeasonTeamsForDouble(1))->all())->toBe([]);
    });
});
