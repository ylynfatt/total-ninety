<?php

use App\Domain\Formats\SingleEliminationGenerator;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;

function stageForKnockout(int $teamCount): Stage
{
    $season = Season::factory()->create();
    $season->teams()->attach(Team::factory()->count($teamCount)->create());

    return Stage::factory()->singleElimination()->create(['season_id' => $season->id]);
}

describe('SingleEliminationGenerator', function () {
    it('produces n/2 round-1 games when n is a power of two', function (int $n) {
        $stage = stageForKnockout($n);

        $pairs = (new SingleEliminationGenerator)->generate($stage);

        expect($pairs)->toHaveCount($n / 2);
    })->with([
        '2 teams' => 2,
        '4 teams' => 4,
        '8 teams' => 8,
        '16 teams' => 16,
        '32 teams' => 32,
    ]);

    it('produces (n - capacity/2) round-1 games with byes for non-powers-of-2', function () {
        // 5 teams → next power of 2 is 8; capacity/2 = 4; round 1 = 5 - 4 = 1 game
        expect((new SingleEliminationGenerator)->generate(stageForKnockout(5)))->toHaveCount(1);

        // 6 teams → capacity 8, round 1 = 6 - 4 = 2 games (2 byes for top seeds)
        expect((new SingleEliminationGenerator)->generate(stageForKnockout(6)))->toHaveCount(2);

        // 7 teams → capacity 8, round 1 = 7 - 4 = 3 games
        expect((new SingleEliminationGenerator)->generate(stageForKnockout(7)))->toHaveCount(3);

        // 12 teams → capacity 16, round 1 = 12 - 8 = 4 games
        expect((new SingleEliminationGenerator)->generate(stageForKnockout(12)))->toHaveCount(4);
    });

    it('pairs lowest seed against highest seed in round 1', function () {
        $season = Season::factory()->create();
        $teams = Team::factory()->count(8)->create();
        $season->teams()->attach($teams);
        $stage = Stage::factory()->singleElimination()->create(['season_id' => $season->id]);

        $pairs = (new SingleEliminationGenerator)->generate($stage);

        // First seed plays last seed in the first game (lowest seed = first in
        // input collection by convention).
        $first = $season->teams->first();
        $last = $season->teams->last();

        $expected = collect($pairs)->first(fn (array $pair) => $pair['home_team_id'] === $first->id);
        expect($expected['away_team_id'])->toBe($last->id);
    });

    it('returns an empty collection for fewer than 2 teams', function () {
        $generator = new SingleEliminationGenerator;

        expect($generator->generate(stageForKnockout(0))->all())->toBe([]);
        expect($generator->generate(stageForKnockout(1))->all())->toBe([]);
    });

    it('always returns null group_id (knockout is not grouped)', function () {
        $stage = stageForKnockout(8);

        $pairs = (new SingleEliminationGenerator)->generate($stage);

        foreach ($pairs as $pair) {
            expect($pair['group_id'])->toBeNull();
        }
    });
});
