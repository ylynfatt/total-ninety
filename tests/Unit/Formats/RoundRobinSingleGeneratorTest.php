<?php

use App\Domain\Formats\RoundRobinSingleGenerator;
use App\Models\Team;

describe('RoundRobinSingleGenerator', function () {
    it('returns an empty collection for fewer than 2 teams', function () {
        $generator = new RoundRobinSingleGenerator;

        expect($generator->generate(collect())->all())->toBe([]);
        expect($generator->generate(collect([Team::factory()->create()]))->all())->toBe([]);
    });

    it('produces n(n-1)/2 fixtures for n teams', function (int $n) {
        $teams = Team::factory()->count($n)->create();

        $pairs = (new RoundRobinSingleGenerator)->generate($teams);

        expect($pairs)->toHaveCount(($n * ($n - 1)) / 2);
    })->with([
        'two teams' => 2,
        'three teams' => 3,
        'four teams' => 4,
        'eight teams' => 8,
        'ten teams' => 10,
    ]);

    it('never pairs a team against itself', function () {
        $teams = Team::factory()->count(6)->create();

        $pairs = (new RoundRobinSingleGenerator)->generate($teams);

        foreach ($pairs as $pair) {
            expect($pair['home_team_id'])->not->toBe($pair['away_team_id']);
        }
    });

    it('ensures each unordered pair appears exactly once', function () {
        $teams = Team::factory()->count(5)->create();

        $pairs = (new RoundRobinSingleGenerator)->generate($teams);
        $unordered = $pairs->map(function (array $pair) {
            $sorted = [$pair['home_team_id'], $pair['away_team_id']];
            sort($sorted);

            return implode('-', $sorted);
        });

        expect($unordered->unique()->count())->toBe($pairs->count());
    });

    it('references only ids from the input team collection', function () {
        $teams = Team::factory()->count(4)->create();
        $ids = $teams->pluck('id')->all();

        $pairs = (new RoundRobinSingleGenerator)->generate($teams);

        foreach ($pairs as $pair) {
            expect($ids)->toContain($pair['home_team_id']);
            expect($ids)->toContain($pair['away_team_id']);
        }
    });
});
