<?php

use App\Enums\StageFormat;
use App\Models\Game;
use App\Models\Group;
use App\Models\League;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;

/**
 * Build league + season + GroupStage + N groups of M teams, returning
 * [league, season, stage].
 *
 * @return array{0: League, 1: Season, 2: Stage}
 */
function groupStageScaffold(int $groupCount = 2, int $teamsPerGroup = 5): array
{
    $league = League::factory()->create();
    $season = Season::factory()->create(['league_id' => $league->id]);
    $stage = Stage::factory()->groupStage()->create(['season_id' => $season->id]);

    for ($g = 0; $g < $groupCount; $g++) {
        $group = Group::factory()->create(['stage_id' => $stage->id]);
        $teams = Team::factory()->count($teamsPerGroup)->create();
        $season->teams()->attach($teams);
        $group->teams()->attach($teams);
    }

    return [$league, $season, $stage];
}

describe('Stage config.legs_per_group validation', function () {
    it('accepts legs_per_group=1 when format is group_stage', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'Group Stage',
                'format' => StageFormat::GroupStage->value,
                'config' => ['legs_per_group' => 1],
            ])
            ->assertRedirect();

        expect(Stage::where('name', 'Group Stage')->firstOrFail()->config)
            ->toBe(['legs_per_group' => 1]);
    });

    it('accepts legs_per_group=2 when format is group_stage', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'Home and Away',
                'format' => StageFormat::GroupStage->value,
                'config' => ['legs_per_group' => 2],
            ])
            ->assertRedirect();

        expect(Stage::where('name', 'Home and Away')->firstOrFail()->config)
            ->toBe(['legs_per_group' => 2]);
    });

    it('rejects legs_per_group=3 (or any other value)', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/{$season->id}/stages/create")
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'Bad Legs',
                'format' => StageFormat::GroupStage->value,
                'config' => ['legs_per_group' => 3],
            ])
            ->assertSessionHasErrors('config.legs_per_group');
    });

    it('drops legs_per_group from config when format is not group_stage', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'RR Single',
                'format' => StageFormat::RoundRobinSingle->value,
                'config' => ['legs_per_group' => 2],
            ])
            ->assertRedirect();

        $stage = Stage::where('name', 'RR Single')->firstOrFail();

        // The config key should be silently dropped — it's meaningless for
        // ungrouped formats, and we don't want it polluting the column.
        expect($stage->config)->toBeNull();
    });

    it('lets the owner update legs_per_group on an existing group_stage', function () {
        [$league, $season, $stage] = groupStageScaffold();

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}", [
                'name' => $stage->name,
                'order' => $stage->order,
                'config' => ['legs_per_group' => 2],
            ])
            ->assertRedirect();

        expect($stage->fresh()->config)->toBe(['legs_per_group' => 2]);
    });
});

describe('GroupStage fixture count varies with legs_per_group', function () {
    it('produces 20 fixtures for 2 groups of 5 teams with default config (1 leg)', function () {
        [$league, $season, $stage] = groupStageScaffold(2, 5);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/generate-fixtures")
            ->assertRedirect();

        // 5 teams per group → 10 games per group → 20 total
        expect(Game::where('stage_id', $stage->id)->count())->toBe(20);
    });

    it('produces 40 fixtures for 2 groups of 5 teams with legs_per_group=2', function () {
        [$league, $season, $stage] = groupStageScaffold(2, 5);
        $stage->update(['config' => ['legs_per_group' => 2]]);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/generate-fixtures")
            ->assertRedirect();

        // 5 teams per group, 2 legs → 20 games per group → 40 total
        expect(Game::where('stage_id', $stage->id)->count())->toBe(40);
    });
});
