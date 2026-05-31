<?php

use App\Domain\Formats\SingleEliminationGenerator;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;
use Illuminate\Support\Collection;

function stageForKnockout(int $teamCount): Stage
{
    $season = Season::factory()->create();
    $season->teams()->attach(Team::factory()->count($teamCount)->create());

    return Stage::factory()->singleElimination()->create(['season_id' => $season->id]);
}

function roundOne(Collection $pairs): Collection
{
    return $pairs->where('round', 1)->values();
}

describe('SingleEliminationGenerator', function () {
    it('generates the full bracket: n - 1 games total', function (int $n) {
        $pairs = (new SingleEliminationGenerator)->generate(stageForKnockout($n));

        expect($pairs)->toHaveCount($n - 1);
    })->with([
        '2 teams' => 2,
        '4 teams' => 4,
        '5 teams' => 5,
        '6 teams' => 6,
        '7 teams' => 7,
        '8 teams' => 8,
        '12 teams' => 12,
        '16 teams' => 16,
    ]);

    it('produces n/2 round-1 games when n is a power of two', function (int $n) {
        $pairs = (new SingleEliminationGenerator)->generate(stageForKnockout($n));

        expect(roundOne($pairs))->toHaveCount($n / 2);
    })->with([
        '2 teams' => 2,
        '4 teams' => 4,
        '8 teams' => 8,
        '16 teams' => 16,
    ]);

    it('produces (n - capacity/2) contested round-1 games with byes', function () {
        // 5 teams → capacity 8; round 1 = 5 - 4 = 1 contested game (3 byes)
        expect(roundOne((new SingleEliminationGenerator)->generate(stageForKnockout(5))))->toHaveCount(1);
        // 6 teams → round 1 = 6 - 4 = 2
        expect(roundOne((new SingleEliminationGenerator)->generate(stageForKnockout(6))))->toHaveCount(2);
        // 7 teams → round 1 = 7 - 4 = 3
        expect(roundOne((new SingleEliminationGenerator)->generate(stageForKnockout(7))))->toHaveCount(3);
        // 12 teams → capacity 16, round 1 = 12 - 8 = 4
        expect(roundOne((new SingleEliminationGenerator)->generate(stageForKnockout(12))))->toHaveCount(4);
    });

    it('lays out the right number of rounds with a single final', function () {
        $pairs = (new SingleEliminationGenerator)->generate(stageForKnockout(8));

        // 8 teams → 3 rounds (QF, SF, F)
        expect($pairs->pluck('round')->unique()->sort()->values()->all())->toBe([1, 2, 3]);
        // The final is one game at the highest round.
        expect($pairs->where('round', 3))->toHaveCount(1);
        expect($pairs->where('round', 2))->toHaveCount(2);
    });

    it('creates later-round games as TBD placeholders', function () {
        $pairs = (new SingleEliminationGenerator)->generate(stageForKnockout(8));

        // With no byes, every round 2+ slot is awaiting winners.
        $pairs->where('round', '>', 1)->each(function (array $game) {
            expect($game['home_team_id'])->toBeNull()
                ->and($game['away_team_id'])->toBeNull();
        });
    });

    it('seeds bye teams directly into their round-2 slots', function () {
        // 5 teams, 3 byes: seeds 1, 2, 3 skip round 1. Round 2 (semifinals)
        // should carry the bye teams; one SF is seed 2 vs seed 3 (both byes).
        $pairs = (new SingleEliminationGenerator)->generate(stageForKnockout(5));

        $filledRound2 = $pairs->where('round', 2)
            ->filter(fn (array $g) => $g['home_team_id'] !== null || $g['away_team_id'] !== null);

        expect($filledRound2)->not->toBeEmpty();
    });

    it('assigns a unique position per game within each round', function () {
        $pairs = (new SingleEliminationGenerator)->generate(stageForKnockout(8));

        $pairs->groupBy('round')->each(function ($games) {
            $positions = $games->pluck('bracket_position');
            expect($positions->unique()->count())->toBe($positions->count());
        });
    });

    it('pairs lowest seed against highest seed in round 1', function () {
        $season = Season::factory()->create();
        $teams = Team::factory()->count(8)->create();
        $season->teams()->attach($teams);
        $stage = Stage::factory()->singleElimination()->create(['season_id' => $season->id]);

        $pairs = (new SingleEliminationGenerator)->generate($stage);

        $first = $season->teams->first();
        $last = $season->teams->last();

        $expected = roundOne($pairs)->first(fn (array $pair) => $pair['home_team_id'] === $first->id);
        expect($expected['away_team_id'])->toBe($last->id);
    });

    it('returns an empty collection for fewer than 2 teams', function () {
        $generator = new SingleEliminationGenerator;

        expect($generator->generate(stageForKnockout(0))->all())->toBe([]);
        expect($generator->generate(stageForKnockout(1))->all())->toBe([]);
    });

    it('always returns null group_id (knockout is not grouped)', function () {
        $pairs = (new SingleEliminationGenerator)->generate(stageForKnockout(8));

        foreach ($pairs as $pair) {
            expect($pair['group_id'])->toBeNull();
        }
    });
});
