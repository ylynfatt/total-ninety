<?php

use App\Models\Game;
use App\Models\League;
use App\Models\Season;
use App\Models\User;
use App\Policies\GamePolicy;
use App\Policies\LeaguePolicy;

describe('LeaguePolicy', function () {
    it('allows guests and authenticated users to view a public league', function () {
        $league = League::factory()->create(['is_public' => true]);
        $owner = $league->owner;
        $stranger = User::factory()->create();

        expect((new LeaguePolicy)->view(null, $league))->toBeTrue();
        expect((new LeaguePolicy)->view($stranger, $league))->toBeTrue();
        expect((new LeaguePolicy)->view($owner, $league))->toBeTrue();
    });

    it('hides a private league from everyone except the owner', function () {
        $league = League::factory()->private()->create();
        $stranger = User::factory()->create();

        expect((new LeaguePolicy)->view(null, $league))->toBeFalse();
        expect((new LeaguePolicy)->view($stranger, $league))->toBeFalse();
        expect((new LeaguePolicy)->view($league->owner, $league))->toBeTrue();
    });

    it('only allows the owner to update or delete a league', function () {
        $league = League::factory()->create();
        $stranger = User::factory()->create();

        expect($stranger->can('update', $league))->toBeFalse();
        expect($stranger->can('delete', $league))->toBeFalse();

        expect($league->owner->can('update', $league))->toBeTrue();
        expect($league->owner->can('delete', $league))->toBeTrue();
    });

    it('lets any authenticated user create a league', function () {
        $user = User::factory()->create();

        expect($user->can('create', League::class))->toBeTrue();
    });
});

describe('SeasonPolicy', function () {
    it('lets a stranger view a season inside a public league', function () {
        $league = League::factory()->create(['is_public' => true]);
        $season = Season::factory()->create(['league_id' => $league->id]);
        $stranger = User::factory()->create();

        expect($stranger->can('view', $season))->toBeTrue();
    });

    it('blocks strangers from editing a season', function () {
        $season = Season::factory()->create();
        $stranger = User::factory()->create();

        expect($stranger->can('update', $season))->toBeFalse();
        expect($season->league->owner->can('update', $season))->toBeTrue();
    });
});

describe('GamePolicy with seasoned games', function () {
    it('lets the league owner update a game inside their league', function () {
        $season = Season::factory()->create();
        $game = Game::factory()->create(['season_id' => $season->id]);

        expect($season->league->owner->can('update', $game))->toBeTrue();
    });

    it('blocks strangers from updating a game inside someone elses league', function () {
        $season = Season::factory()->create();
        $game = Game::factory()->create(['season_id' => $season->id]);
        $stranger = User::factory()->create();

        expect($stranger->can('update', $game))->toBeFalse();
    });

    it('keeps legacy games (no season) publicly editable', function () {
        $game = Game::factory()->create();

        expect((new GamePolicy)->update(null, $game))->toBeTrue();
    });
});
