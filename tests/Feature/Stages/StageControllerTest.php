<?php

use App\Enums\StageFormat;
use App\Models\Game;
use App\Models\Group;
use App\Models\League;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;
use App\Models\User;

/**
 * Helper: build a league + season with N teams attached, return [league, season].
 *
 * @return array{0: League, 1: Season}
 */
function leagueSeasonWithTeams(int $teamCount, bool $publicLeague = true): array
{
    $league = League::factory()->create(['is_public' => $publicLeague]);
    $season = Season::factory()->create(['league_id' => $league->id]);

    if ($teamCount > 0) {
        $season->teams()->attach(Team::factory()->count($teamCount)->create());
    }

    return [$league, $season];
}

describe('StagesController create / store', function () {
    it('requires authentication for the create form', function () {
        [$league, $season] = leagueSeasonWithTeams(4);

        $this->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/create")
            ->assertRedirect('/login');
    });

    it('forbids non-owners from creating a stage', function () {
        [$league, $season] = leagueSeasonWithTeams(4);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'Regular Season',
                'format' => StageFormat::RoundRobinSingle->value,
                'order' => 10,
            ])
            ->assertForbidden();
    });

    it('lets the league owner create a stage', function () {
        [$league, $season] = leagueSeasonWithTeams(4);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'Regular Season',
                'format' => StageFormat::RoundRobinSingle->value,
                'order' => 10,
            ])
            ->assertRedirect();

        $stage = Stage::where('name', 'Regular Season')->firstOrFail();
        expect($stage->season_id)->toBe($season->id);
        expect($stage->format)->toBe(StageFormat::RoundRobinSingle);
    });

    it('rejects an invalid format', function () {
        [$league, $season] = leagueSeasonWithTeams(4);

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/{$season->id}/stages/create")
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'Stage',
                'format' => 'made_up_format',
            ])
            ->assertSessionHasErrors('format');
    });

    it('rejects a duplicate stage name within the same season', function () {
        [$league, $season] = leagueSeasonWithTeams(4);
        Stage::factory()->create(['season_id' => $season->id, 'name' => 'Taken']);

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/{$season->id}/stages/create")
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'Taken',
                'format' => StageFormat::RoundRobinSingle->value,
            ])
            ->assertSessionHasErrors('name');
    });
});

describe('StagesController show / update / destroy', function () {
    it('renders a stage on a public league for anyone', function () {
        [$league, $season] = leagueSeasonWithTeams(4);
        $stage = Stage::factory()->create(['season_id' => $season->id]);

        $this->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Stages/Show')
                ->where('stage.id', $stage->id)
            );
    });

    it('404s on a cross-season URL', function () {
        [$league, $seasonA] = leagueSeasonWithTeams(4);
        $seasonB = Season::factory()->create(['league_id' => $league->id]);
        $stageInB = Stage::factory()->create(['season_id' => $seasonB->id]);

        $this->get("/leagues/{$league->slug}/seasons/{$seasonA->id}/stages/{$stageInB->id}")
            ->assertNotFound();
    });

    it('does not allow format to be changed on update', function () {
        [$league, $season] = leagueSeasonWithTeams(4);
        $stage = Stage::factory()->create([
            'season_id' => $season->id,
            'format' => StageFormat::RoundRobinSingle,
        ]);

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}", [
                'name' => $stage->name,
                'format' => StageFormat::RoundRobinDouble->value,
                'order' => $stage->order,
            ])
            ->assertRedirect();

        expect($stage->fresh()->format)->toBe(StageFormat::RoundRobinSingle);
    });

    it('lets the owner delete a stage', function () {
        [$league, $season] = leagueSeasonWithTeams(4);
        $stage = Stage::factory()->create(['season_id' => $season->id]);

        $this->actingAs($league->owner)
            ->delete("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}")
            ->assertRedirect("/leagues/{$league->slug}/seasons/{$season->id}");

        expect(Stage::find($stage->id))->toBeNull();
    });
});

describe('StagesController generateFixtures', function () {
    it('persists n(n-1)/2 games for a RoundRobinSingle stage', function () {
        [$league, $season] = leagueSeasonWithTeams(6);
        $stage = Stage::factory()->create([
            'season_id' => $season->id,
            'format' => StageFormat::RoundRobinSingle,
        ]);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/generate-fixtures")
            ->assertRedirect();

        expect(Game::where('stage_id', $stage->id)->count())->toBe(15);
    });

    it('persists n(n-1) games for a RoundRobinDouble stage', function () {
        [$league, $season] = leagueSeasonWithTeams(4);
        $stage = Stage::factory()->roundRobinDouble()->create(['season_id' => $season->id]);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/generate-fixtures")
            ->assertRedirect();

        expect(Game::where('stage_id', $stage->id)->count())->toBe(12);
    });

    it('persists round-1 bracket games for SingleElimination', function () {
        [$league, $season] = leagueSeasonWithTeams(8);
        $stage = Stage::factory()->singleElimination()->create(['season_id' => $season->id]);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/generate-fixtures")
            ->assertRedirect();

        expect(Game::where('stage_id', $stage->id)->count())->toBe(4);
    });

    it('surfaces "already has fixtures" as a session error on re-run', function () {
        [$league, $season] = leagueSeasonWithTeams(4);
        $stage = Stage::factory()->create([
            'season_id' => $season->id,
            'format' => StageFormat::RoundRobinSingle,
        ]);
        Game::factory()->create(['stage_id' => $stage->id, 'season_id' => $season->id]);

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}")
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/generate-fixtures")
            ->assertRedirect()
            ->assertSessionHasErrors('fixtures');
    });

    it('surfaces "no teams" as a session error', function () {
        [$league, $season] = leagueSeasonWithTeams(0);
        $stage = Stage::factory()->create([
            'season_id' => $season->id,
            'format' => StageFormat::RoundRobinSingle,
        ]);

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}")
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/generate-fixtures")
            ->assertSessionHasErrors('fixtures');
    });

    it('surfaces "no groups defined" for an empty GroupStage', function () {
        [$league, $season] = leagueSeasonWithTeams(4);
        $stage = Stage::factory()->groupStage()->create(['season_id' => $season->id]);

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}")
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/generate-fixtures")
            ->assertSessionHasErrors('fixtures');
    });

    it('persists per-group fixtures when GroupStage has groups with teams', function () {
        [$league, $season] = leagueSeasonWithTeams(0);
        $stage = Stage::factory()->groupStage()->create(['season_id' => $season->id]);

        // Two groups of 3 teams each.
        Group::factory()->create(['stage_id' => $stage->id])
            ->teams()->attach(Team::factory()->count(3)->create());
        Group::factory()->create(['stage_id' => $stage->id])
            ->teams()->attach(Team::factory()->count(3)->create());

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/generate-fixtures")
            ->assertRedirect();

        // 3 games per group × 2 groups = 6
        expect(Game::where('stage_id', $stage->id)->count())->toBe(6);
    });

    it('forbids strangers from generating fixtures', function () {
        [$league, $season] = leagueSeasonWithTeams(4);
        $stage = Stage::factory()->create([
            'season_id' => $season->id,
            'format' => StageFormat::RoundRobinSingle,
        ]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/generate-fixtures")
            ->assertForbidden();

        expect(Game::where('stage_id', $stage->id)->count())->toBe(0);
    });
});
