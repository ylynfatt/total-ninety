<?php

use App\Domain\Formats\RoundRobinDoubleGenerator;
use App\Domain\Formats\RoundRobinSingleGenerator;
use App\Models\Team;

describe('RoundRobinDoubleGenerator', function () {
    it('produces n(n-1) fixtures for n teams', function (int $n) {
        $teams = Team::factory()->count($n)->create();

        $pairs = (new RoundRobinDoubleGenerator(new RoundRobinSingleGenerator))->generate($teams);

        expect($pairs)->toHaveCount($n * ($n - 1));
    })->with([
        'two teams' => 2,
        'three teams' => 3,
        'four teams' => 4,
        'eight teams' => 8,
        'twenty teams (premier league size)' => 20,
    ]);

    it('produces each unordered pair exactly twice', function () {
        $teams = Team::factory()->count(5)->create();

        $pairs = (new RoundRobinDoubleGenerator(new RoundRobinSingleGenerator))->generate($teams);

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
        $teams = Team::factory()->count(4)->create();

        $pairs = (new RoundRobinDoubleGenerator(new RoundRobinSingleGenerator))->generate($teams);

        $ordered = $pairs->map(fn (array $pair) => $pair['home_team_id'].':'.$pair['away_team_id']);

        expect($ordered->unique()->count())->toBe($pairs->count());
    });

    it('returns an empty collection for fewer than 2 teams', function () {
        $generator = new RoundRobinDoubleGenerator(new RoundRobinSingleGenerator);

        expect($generator->generate(collect())->all())->toBe([]);
        expect($generator->generate(collect([Team::factory()->create()]))->all())->toBe([]);
    });
});
