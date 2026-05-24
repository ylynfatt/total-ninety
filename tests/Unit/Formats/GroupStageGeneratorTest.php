<?php

use App\Domain\Formats\GroupStageGenerator;
use App\Enums\StageFormat;
use App\Models\Group;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Helper: build a GroupStage with N groups of M teams each.
 *
 * @return array{0: Stage, 1: Collection<int, Group>}
 */
function groupStageWith(int $groupCount, int $teamsPerGroup): array
{
    $season = Season::factory()->create();
    $stage = Stage::factory()->create([
        'season_id' => $season->id,
        'format' => StageFormat::GroupStage,
    ]);

    $groups = collect();
    for ($g = 0; $g < $groupCount; $g++) {
        $group = Group::factory()->create(['stage_id' => $stage->id]);
        $group->teams()->attach(Team::factory()->count($teamsPerGroup)->create());
        $groups->push($group);
    }

    return [$stage, $groups];
}

describe('GroupStageGenerator', function () {
    it('produces n(n-1)/2 games per group', function () {
        [$stage, $groups] = groupStageWith(groupCount: 2, teamsPerGroup: 4);

        $pairs = (new GroupStageGenerator)->generate($stage);

        // 4 teams per group → 6 fixtures per group × 2 groups = 12
        expect($pairs)->toHaveCount(12);
    });

    it('tags every pair with its originating group_id', function () {
        [$stage, $groups] = groupStageWith(groupCount: 3, teamsPerGroup: 3);

        $pairs = (new GroupStageGenerator)->generate($stage);

        $groupIds = $groups->pluck('id')->all();
        foreach ($pairs as $pair) {
            expect($groupIds)->toContain($pair['group_id']);
        }
    });

    it('only pairs teams within the same group, never across', function () {
        [$stage, $groups] = groupStageWith(groupCount: 2, teamsPerGroup: 4);

        $pairs = (new GroupStageGenerator)->generate($stage);

        $teamToGroup = collect();
        foreach ($groups as $group) {
            foreach ($group->teams as $team) {
                $teamToGroup[$team->id] = $group->id;
            }
        }

        foreach ($pairs as $pair) {
            expect($teamToGroup[$pair['home_team_id']])->toBe($pair['group_id']);
            expect($teamToGroup[$pair['away_team_id']])->toBe($pair['group_id']);
        }
    });

    it('throws when the stage has no groups defined', function () {
        $stage = Stage::factory()->create(['format' => StageFormat::GroupStage]);

        expect(fn () => (new GroupStageGenerator)->generate($stage))
            ->toThrow(DomainException::class, 'no groups defined');
    });

    it('supports multi-leg groups via stage.config[legs_per_group]', function () {
        [$stage, $groups] = groupStageWith(groupCount: 1, teamsPerGroup: 4);
        $stage->update(['config' => ['legs_per_group' => 2]]);

        $pairs = (new GroupStageGenerator)->generate($stage);

        // 4 teams, 2 legs → 4*3 = 12 fixtures
        expect($pairs)->toHaveCount(12);
    });

    it('defaults to 1 leg when stage.config is null', function () {
        [$stage, $groups] = groupStageWith(groupCount: 1, teamsPerGroup: 5);

        $pairs = (new GroupStageGenerator)->generate($stage);

        // 5 teams, 1 leg → 5*4/2 = 10 fixtures
        expect($pairs)->toHaveCount(10);
    });
});
