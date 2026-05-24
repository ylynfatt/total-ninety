<?php

use App\Models\League;
use App\Models\User;

describe('LeaguesController index', function () {
    it('renders the index page for unauthenticated visitors', function () {
        $this->get('/leagues')->assertOk();
    });

    it('lists public leagues to anonymous visitors', function () {
        $public = League::factory()->create(['is_public' => true, 'name' => 'Public League']);
        League::factory()->private()->create(['name' => 'Private League']);

        $response = $this->get('/leagues');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Leagues/Index')
            ->has('leagues', 1)
            ->where('leagues.0.name', 'Public League')
        );
    });

    it('includes the auth users own private leagues in the index', function () {
        $user = User::factory()->create();
        League::factory()->create(['is_public' => true]);
        League::factory()->private()->create(['user_id' => $user->id, 'name' => 'My Private']);

        $response = $this->actingAs($user)->get('/leagues');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('leagues', 2)
        );
    });
});

describe('LeaguesController show', function () {
    it('renders a public league for anonymous visitors', function () {
        $league = League::factory()->create(['is_public' => true, 'name' => 'Public']);

        $this->get("/leagues/{$league->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leagues/Show')
                ->where('league.name', 'Public')
                ->where('can.update', false)
                ->where('can.delete', false)
            );
    });

    it('forbids strangers from viewing a private league', function () {
        $league = League::factory()->private()->create();

        $this->get("/leagues/{$league->slug}")->assertForbidden();
    });

    it('exposes can.update + can.delete to the owner', function () {
        $league = League::factory()->create();

        $this->actingAs($league->owner)
            ->get("/leagues/{$league->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.update', true)
                ->where('can.delete', true)
            );
    });
});

describe('LeaguesController create / store', function () {
    it('requires authentication to access the create form', function () {
        $this->get('/leagues/create')->assertRedirect('/login');
    });

    it('lets an authenticated user create a league', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/leagues', [
            'name' => 'My New League',
            'country' => 'USA',
            'description' => 'A test league.',
            'is_public' => true,
        ])->assertRedirect();

        $league = League::where('name', 'My New League')->firstOrFail();
        expect($league->user_id)->toBe($user->id);
        expect($league->slug)->toBe('my-new-league');
        expect($league->is_public)->toBeTrue();
    });

    it('auto-generates a slug when none provided', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/leagues', [
            'name' => 'Auto Slug League',
        ]);

        expect(League::where('slug', 'auto-slug-league')->exists())->toBeTrue();
    });

    it('rejects a duplicate slug', function () {
        $user = User::factory()->create();
        League::factory()->create(['slug' => 'taken']);

        $this->actingAs($user)
            ->from('/leagues/create')
            ->post('/leagues', [
                'name' => 'New League',
                'slug' => 'taken',
            ])
            ->assertRedirect('/leagues/create')
            ->assertSessionHasErrors('slug');
    });

    it('rejects an empty name', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/leagues/create')
            ->post('/leagues', ['name' => ''])
            ->assertSessionHasErrors('name');
    });
});

describe('LeaguesController update', function () {
    it('lets the owner update their league', function () {
        $league = League::factory()->create(['name' => 'Old Name']);

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}", [
                'name' => 'New Name',
                'slug' => $league->slug,
                'description' => 'Updated description',
                'country' => $league->country,
                'is_public' => true,
            ])
            ->assertRedirect();

        expect($league->fresh()->name)->toBe('New Name');
        expect($league->fresh()->description)->toBe('Updated description');
    });

    it('forbids a stranger from updating someone elses league', function () {
        $league = League::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->put("/leagues/{$league->slug}", [
                'name' => 'Hacked',
                'slug' => $league->slug,
            ])
            ->assertForbidden();

        expect($league->fresh()->name)->not->toBe('Hacked');
    });

    it('allows the owner to keep their existing slug on update', function () {
        $league = League::factory()->create(['slug' => 'mine']);

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}", [
                'name' => $league->name,
                'slug' => 'mine',
            ])
            ->assertRedirect();

        expect($league->fresh()->slug)->toBe('mine');
    });
});

describe('LeaguesController destroy', function () {
    it('lets the owner delete their league', function () {
        $league = League::factory()->create();
        $slug = $league->slug;

        $this->actingAs($league->owner)
            ->delete("/leagues/{$league->slug}")
            ->assertRedirect('/leagues');

        expect(League::where('slug', $slug)->exists())->toBeFalse();
    });

    it('forbids a stranger from deleting someone elses league', function () {
        $league = League::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->delete("/leagues/{$league->slug}")
            ->assertForbidden();

        expect(League::find($league->id))->not->toBeNull();
    });
});
