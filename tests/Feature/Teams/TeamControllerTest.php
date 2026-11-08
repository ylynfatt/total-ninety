<?php

use App\Models\Game;
use App\Models\League;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

describe('TeamsController index + show', function () {
    it('renders the index page for unauthenticated visitors', function () {
        Team::factory()->count(3)->create();

        $this->get('/teams')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Teams/Index')
                ->has('teams', 3)
            );
    });

    it('renders the show page for unauthenticated visitors', function () {
        $team = Team::factory()->create(['name' => 'Public FC']);

        $this->get("/teams/{$team->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Teams/Show')
                ->where('team.name', 'Public FC')
                ->where('can.update', false)
                ->where('can.delete', false)
            );
    });

    it('shows the team a season-membership list with league context', function () {
        $team = Team::factory()->create();
        $season = Season::factory()->create();
        $season->teams()->attach($team);

        $this->get("/teams/{$team->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('team.seasons', 1)
                ->where('team.seasons.0.id', $season->id)
                ->where('team.seasons.0.league.id', $season->league_id)
            );
    });
});

describe('TeamsController create / store', function () {
    it('requires authentication to access the create form', function () {
        $this->get('/teams/create')->assertRedirect('/login');
    });

    it('lets an authenticated user create a team', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/teams', [
                'name' => 'Test FC',
                'acronym' => 'TFC',
                'year_founded' => 1950,
                'home_ground' => 'Test Stadium',
            ])
            ->assertRedirect();

        $team = Team::where('name', 'Test FC')->firstOrFail();
        expect($team->user_id)->toBe($user->id);
        expect($team->acronym)->toBe('TFC');
        expect($team->year_founded)->toBe(1950);
    });

    it('auto-uppercases the acronym on submit', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/teams', [
                'name' => 'Lowercase Test',
                'acronym' => 'lct',
                'year_founded' => 1980,
            ])
            ->assertRedirect();

        expect(Team::where('name', 'Lowercase Test')->value('acronym'))->toBe('LCT');
    });

    it('rejects an acronym that is not exactly 3 letters', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/teams/create')
            ->post('/teams', [
                'name' => 'Bad Acronym FC',
                'acronym' => 'ABCD',
                'year_founded' => 1980,
            ])
            ->assertSessionHasErrors('acronym');
    });

    it('rejects a duplicate acronym', function () {
        $user = User::factory()->create();
        Team::factory()->create(['acronym' => 'TKN']);

        $this->actingAs($user)
            ->from('/teams/create')
            ->post('/teams', [
                'name' => 'New Team',
                'acronym' => 'TKN',
                'year_founded' => 1980,
            ])
            ->assertSessionHasErrors('acronym');
    });

    it('rejects a future year_founded', function () {
        $user = User::factory()->create();
        $future = (int) date('Y') + 1;

        $this->actingAs($user)
            ->from('/teams/create')
            ->post('/teams', [
                'name' => 'Time Traveler FC',
                'acronym' => 'TTF',
                'year_founded' => $future,
            ])
            ->assertSessionHasErrors('year_founded');
    });

    it('rejects a year_founded before 1800', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/teams/create')
            ->post('/teams', [
                'name' => 'Ancient FC',
                'acronym' => 'ANC',
                'year_founded' => 1700,
            ])
            ->assertSessionHasErrors('year_founded');
    });
});

describe('TeamsController update', function () {
    it('requires authentication', function () {
        $team = Team::factory()->create();

        $this->put("/teams/{$team->id}", ['name' => 'Hacked'])
            ->assertRedirect('/login');
    });

    it('lets the team owner update their team', function () {
        $team = Team::factory()->create(['name' => 'Old Name']);
        $user = $team->owner;

        $this->actingAs($user)
            ->put("/teams/{$team->id}", [
                'name' => 'New Name',
                'acronym' => $team->acronym,
                'year_founded' => $team->year_founded,
            ])
            ->assertRedirect();

        expect($team->fresh()->name)->toBe('New Name');
    });

    it('allows the owner to keep their existing acronym on update', function () {
        $team = Team::factory()->create(['acronym' => 'KPT']);
        $user = $team->owner;

        $this->actingAs($user)
            ->put("/teams/{$team->id}", [
                'name' => $team->name,
                'acronym' => 'KPT',
                'year_founded' => $team->year_founded,
            ])
            ->assertRedirect();

        expect($team->fresh()->acronym)->toBe('KPT');
    });
});

describe('TeamsController destroy', function () {
    it('lets an authenticated user delete a team that has no season attachments', function () {
        $team = Team::factory()->create();
        $user = $team->owner;

        $this->actingAs($user)
            ->delete("/teams/{$team->id}")
            ->assertRedirect('/teams');

        expect(Team::find($team->id))->toBeNull();
    });

    it('refuses to delete a team that is attached to a season', function () {
        $team = Team::factory()->create();
        $season = Season::factory()->create();
        $season->teams()->attach($team);

        $user = $team->owner;

        $this->actingAs($user)
            ->from("/teams/{$team->id}")
            ->delete("/teams/{$team->id}")
            ->assertSessionHasErrors('delete');

        expect(Team::find($team->id))->not->toBeNull();
    });
});

it('forbids unrelated users and leaves unowned legacy teams read-only', function (): void {
    $user = User::factory()->create();
    $owned = Team::factory()->create();
    $legacy = Team::factory()->create(['user_id' => null]);

    foreach ([$owned, $legacy] as $team) {
        $this->actingAs($user)->get(route('teams.edit', $team))->assertForbidden();
        $this->put(route('teams.update', $team), [
            'name' => 'Unauthorized', 'acronym' => $team->acronym, 'year_founded' => $team->year_founded,
        ])->assertForbidden();
        $this->delete(route('teams.destroy', $team))->assertForbidden();
        $this->get(route('teams.show', $team))->assertInertia(fn ($page) => $page
            ->where('can.update', false)->where('can.delete', false));
    }
});

it('refuses to delete a team referenced by a fixture even without a season attachment', function (): void {
    $team = Team::factory()->create();
    Game::factory()->create(['home_team_id' => $team->id]);

    $this->actingAs($team->owner)->delete(route('teams.destroy', $team))->assertSessionHasErrors('delete');
    expect($team->fresh())->not->toBeNull();
});

it('hides deletion for teams attached to a private season owned by someone else', function (): void {
    $team = Team::factory()->create();
    $season = Season::factory()->for(League::factory()->private())->create();
    $season->teams()->attach($team);

    $this->actingAs($team->owner)->get(route('teams.show', $team))
        ->assertInertia(fn ($page) => $page->where('can.delete', false));
});
