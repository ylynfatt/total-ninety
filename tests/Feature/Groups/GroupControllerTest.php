<?php

use App\Models\Group;
use App\Models\League;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Helper: build league + season (with N attached teams) + a GroupStage on
 * that season. Returns [league, season, stage, teams].
 *
 * @return array{0: League, 1: Season, 2: Stage, 3: Collection<int, Team>}
 */
function groupedChain(int $teamCount = 4): array
{
    $league = League::factory()->create(['is_public' => true]);
    $season = Season::factory()->create(['league_id' => $league->id]);
    $teams = Team::factory()->count($teamCount)->create();
    $season->teams()->attach($teams);
    $stage = Stage::factory()->groupStage()->create(['season_id' => $season->id]);

    return [$league, $season, $stage, $teams];
}

describe('GroupsController create / store', function () {
    it('requires authentication for the create form', function () {
        [$league, $season, $stage] = groupedChain();

        $this->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups/create")
            ->assertRedirect('/login');
    });

    it('forbids non-owners from creating a group', function () {
        [$league, $season, $stage] = groupedChain();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups", [
                'name' => 'Group A',
            ])
            ->assertForbidden();
    });

    it('lets the league owner create a group', function () {
        [$league, $season, $stage] = groupedChain();

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups", [
                'name' => 'Group A',
                'order' => 10,
            ])
            ->assertRedirect("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}");

        $group = Group::where('name', 'Group A')->firstOrFail();
        expect($group->stage_id)->toBe($stage->id);
    });

    it('rejects a duplicate group name within the same stage', function () {
        [$league, $season, $stage] = groupedChain();
        Group::factory()->create(['stage_id' => $stage->id, 'name' => 'Taken']);

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups/create")
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups", [
                'name' => 'Taken',
            ])
            ->assertSessionHasErrors('name');
    });
});

describe('GroupsController scope guards', function () {
    it('404s when the group does not belong to the stage in the URL', function () {
        [$leagueA, $seasonA, $stageA] = groupedChain();
        [$leagueB, $seasonB, $stageB] = groupedChain();
        $groupInB = Group::factory()->create(['stage_id' => $stageB->id]);

        $this->actingAs($leagueA->owner)
            ->get("/leagues/{$leagueA->slug}/seasons/{$seasonA->id}/stages/{$stageA->id}/groups/{$groupInB->id}/edit")
            ->assertNotFound();
    });

    it('404s when the stage does not belong to the season in the URL', function () {
        [$league, $seasonA, $stageA] = groupedChain();
        $seasonB = Season::factory()->create(['league_id' => $league->id]);
        $group = Group::factory()->create(['stage_id' => $stageA->id]);

        $this->actingAs($league->owner)
            ->get("/leagues/{$league->slug}/seasons/{$seasonB->id}/stages/{$stageA->id}/groups/{$group->id}/edit")
            ->assertNotFound();
    });
});

describe('GroupsController update / destroy', function () {
    it('lets the league owner update a group', function () {
        [$league, $season, $stage] = groupedChain();
        $group = Group::factory()->create(['stage_id' => $stage->id, 'name' => 'Old']);

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups/{$group->id}", [
                'name' => 'New',
                'order' => 20,
            ])
            ->assertRedirect();

        expect($group->fresh()->name)->toBe('New');
        expect($group->fresh()->order)->toBe(20);
    });

    it('lets the owner delete a group', function () {
        [$league, $season, $stage] = groupedChain();
        $group = Group::factory()->create(['stage_id' => $stage->id]);

        $this->actingAs($league->owner)
            ->delete("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups/{$group->id}")
            ->assertRedirect();

        expect(Group::find($group->id))->toBeNull();
    });

    it('forbids strangers from updating or deleting a group', function () {
        [$league, $season, $stage] = groupedChain();
        $group = Group::factory()->create(['stage_id' => $stage->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->delete("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups/{$group->id}")
            ->assertForbidden();

        expect(Group::find($group->id))->not->toBeNull();
    });
});

describe('GroupsController team sync', function () {
    it('lets the owner sync a group roster from the season teams', function () {
        [$league, $season, $stage, $teams] = groupedChain(4);
        $group = Group::factory()->create(['stage_id' => $stage->id]);

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups/{$group->id}/teams", [
                'team_ids' => $teams->take(2)->pluck('id')->all(),
            ])
            ->assertRedirect();

        expect($group->fresh()->teams)->toHaveCount(2);
    });

    it('rejects team_ids that are not in the parent season roster', function () {
        [$league, $season, $stage] = groupedChain(4);
        $group = Group::factory()->create(['stage_id' => $stage->id]);
        $outsider = Team::factory()->create(); // not attached to the season

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups/{$group->id}/teams")
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups/{$group->id}/teams", [
                'team_ids' => [$outsider->id],
            ])
            ->assertSessionHasErrors('team_ids.0');

        expect($group->fresh()->teams)->toHaveCount(0);
    });

    it('detaches teams that are removed from the sync payload', function () {
        [$league, $season, $stage, $teams] = groupedChain(4);
        $group = Group::factory()->create(['stage_id' => $stage->id]);
        $group->teams()->attach($teams);

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups/{$group->id}/teams", [
                'team_ids' => [$teams->first()->id],
            ])
            ->assertRedirect();

        expect($group->fresh()->teams)->toHaveCount(1);
    });

    it('forbids strangers from syncing a group roster', function () {
        [$league, $season, $stage, $teams] = groupedChain();
        $group = Group::factory()->create(['stage_id' => $stage->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/groups/{$group->id}/teams", [
                'team_ids' => [$teams->first()->id],
            ])
            ->assertForbidden();
    });
});
