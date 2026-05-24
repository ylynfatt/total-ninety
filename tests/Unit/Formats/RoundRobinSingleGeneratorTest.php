<?php

use App\Domain\Formats\RoundRobinSingleGenerator;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;

/**
 * Helper: build a stage with N teams attached to its season.
 */
function stageWithSeasonTeams(int $teamCount): Stage
{
    $season = Season::factory()->create();
    $season->teams()->attach(Team::factory()->count($teamCount)->create());

    return Stage::factory()->create(['season_id' => $season->id]);
}

describe('RoundRobinSingleGenerator', function () {
    it('returns an empty collection for fewer than 2 teams via Stage', function () {
        $generator = new RoundRobinSingleGenerator;

        expect($generator->generate(stageWithSeasonTeams(0))->all())->toBe([]);
        expect($generator->generate(stageWithSeasonTeams(1))->all())->toBe([]);
    });

    it('produces n(n-1)/2 fixtures for n teams', function (int $n) {
        $stage = stageWithSeasonTeams($n);

        $pairs = (new RoundRobinSingleGenerator)->generate($stage);

        expect($pairs)->toHaveCount(($n * ($n - 1)) / 2);
    })->with([
        'two teams' => 2,
        'three teams' => 3,
        'four teams' => 4,
        'eight teams' => 8,
        'ten teams' => 10,
    ]);

    it('never pairs a team against itself', function () {
        $stage = stageWithSeasonTeams(6);

        $pairs = (new RoundRobinSingleGenerator)->generate($stage);

        foreach ($pairs as $pair) {
            expect($pair['home_team_id'])->not->toBe($pair['away_team_id']);
        }
    });

    it('ensures each unordered pair appears exactly once', function () {
        $stage = stageWithSeasonTeams(5);

        $pairs = (new RoundRobinSingleGenerator)->generate($stage);
        $unordered = $pairs->map(function (array $pair) {
            $sorted = [$pair['home_team_id'], $pair['away_team_id']];
            sort($sorted);

            return implode('-', $sorted);
        });

        expect($unordered->unique()->count())->toBe($pairs->count());
    });

    it('returns null group_id on every pair', function () {
        $stage = stageWithSeasonTeams(4);

        $pairs = (new RoundRobinSingleGenerator)->generate($stage);

        foreach ($pairs as $pair) {
            expect($pair['group_id'])->toBeNull();
        }
    });
});

describe('RoundRobinSingleGenerator::pairsFor()', function () {
    it('is exposed as a reusable static helper for grouped formats', function () {
        $teams = Team::factory()->count(4)->create();

        $pairs = RoundRobinSingleGenerator::pairsFor($teams);

        expect($pairs)->toHaveCount(6);
        foreach ($pairs as $pair) {
            expect($pair['group_id'])->toBeNull();
        }
    });
});
