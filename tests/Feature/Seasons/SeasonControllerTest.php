<?php

use App\Models\League;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

describe('SeasonsController create / store', function () {
    it('requires authentication', function () {
        $league = League::factory()->create();

        $this->get("/leagues/{$league->slug}/seasons/create")->assertRedirect('/login');
    });

    it('forbids non-owners from accessing the create form', function () {
        $league = League::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get("/leagues/{$league->slug}/seasons/create")
            ->assertForbidden();
    });

    it('lets the league owner create a season', function () {
        $league = League::factory()->create();

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons", [
                'name' => '2025/26',
                'starts_on' => '2025-08-01',
                'ends_on' => '2026-05-31',
                'is_active' => true,
            ])
            ->assertRedirect();

        $season = Season::where('name', '2025/26')->firstOrFail();
        expect($season->league_id)->toBe($league->id);
        expect($season->is_active)->toBeTrue();
    });

    it('rejects an end date that is before the start date', function () {
        $league = League::factory()->create();

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/create")
            ->post("/leagues/{$league->slug}/seasons", [
                'name' => 'Bad Dates',
                'starts_on' => '2025-08-01',
                'ends_on' => '2025-07-01',
            ])
            ->assertSessionHasErrors('ends_on');
    });

    it('rejects a duplicate season name within the same league', function () {
        $league = League::factory()->create();
        Season::factory()->create(['league_id' => $league->id, 'name' => 'Taken']);

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/create")
            ->post("/leagues/{$league->slug}/seasons", [
                'name' => 'Taken',
                'starts_on' => '2025-08-01',
            ])
            ->assertSessionHasErrors('name');
    });

    it('allows the same season name across different leagues', function () {
        $league1 = League::factory()->create();
        $league2 = League::factory()->create();
        Season::factory()->create(['league_id' => $league1->id, 'name' => '2025/26']);

        $this->actingAs($league2->owner)
            ->post("/leagues/{$league2->slug}/seasons", [
                'name' => '2025/26',
                'starts_on' => '2025-08-01',
            ])
            ->assertSessionHasNoErrors();
    });
});

describe('SeasonsController show', function () {
    it('renders a season inside a public league for anyone', function () {
        $league = League::factory()->create(['is_public' => true]);
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->get("/leagues/{$league->slug}/seasons/{$season->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Seasons/Show')
                ->where('season.id', $season->id)
            );
    });

    it('forbids strangers from viewing a season inside a private league', function () {
        $league = League::factory()->private()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->get("/leagues/{$league->slug}/seasons/{$season->id}")->assertForbidden();
    });

    it('404s when the season does not belong to the league in the URL', function () {
        $leagueA = League::factory()->create();
        $leagueB = League::factory()->create();
        $seasonInB = Season::factory()->create(['league_id' => $leagueB->id]);

        $this->get("/leagues/{$leagueA->slug}/seasons/{$seasonInB->id}")->assertNotFound();
    });
});

describe('SeasonsController update / destroy', function () {
    it('lets the league owner update a season', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id, 'name' => 'Old']);

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}", [
                'name' => 'New',
                'starts_on' => $season->starts_on->format('Y-m-d'),
                'ends_on' => $season->ends_on?->format('Y-m-d'),
                'is_active' => $season->is_active,
            ])
            ->assertRedirect();

        expect($season->fresh()->name)->toBe('New');
    });

    it('forbids a stranger from updating a season', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}", [
                'name' => 'Hacked',
                'starts_on' => $season->starts_on->format('Y-m-d'),
            ])
            ->assertForbidden();
    });

    it('lets the league owner delete a season', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->actingAs($league->owner)
            ->delete("/leagues/{$league->slug}/seasons/{$season->id}")
            ->assertRedirect("/leagues/{$league->slug}");

        expect(Season::find($season->id))->toBeNull();
    });
});

describe('Seasons team picker', function () {
    it('lets the league owner sync the season roster', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);
        $teams = Team::factory()->count(3)->create();

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/teams", [
                'team_ids' => $teams->pluck('id')->all(),
            ])
            ->assertRedirect();

        expect($season->fresh()->teams)->toHaveCount(3);
    });

    it('removes teams that were dropped from the sync payload', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);
        $teams = Team::factory()->count(3)->create();
        $season->teams()->attach($teams);

        // Sync with just the first team — the other two should detach.
        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/teams", [
                'team_ids' => [$teams->first()->id],
            ])
            ->assertRedirect();

        expect($season->fresh()->teams)->toHaveCount(1);
    });

    it('detaches all teams when called with an empty array', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);
        $season->teams()->attach(Team::factory()->count(3)->create());

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/teams", [
                'team_ids' => [],
            ])
            ->assertRedirect();

        expect($season->fresh()->teams)->toHaveCount(0);
    });

    it('forbids strangers from syncing the roster', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);
        $team = Team::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/teams", [
                'team_ids' => [$team->id],
            ])
            ->assertForbidden();

        expect($season->fresh()->teams)->toHaveCount(0);
    });

    it('rejects team_ids that do not exist', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/{$season->id}/teams")
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/teams", [
                'team_ids' => [9999],
            ])
            ->assertSessionHasErrors('team_ids.0');
    });
});
