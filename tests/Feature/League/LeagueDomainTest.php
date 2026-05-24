<?php

use App\Models\Game;
use App\Models\League;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

describe('League domain relationships', function () {
    it('belongs to an owner via User', function () {
        $league = League::factory()->create();

        expect($league->owner())->toBeInstanceOf(BelongsTo::class);
        expect($league->owner)->toBeInstanceOf(User::class);
    });

    it('exposes leagues via User::leagues()', function () {
        $user = User::factory()->create();
        League::factory()->count(2)->create(['user_id' => $user->id]);
        League::factory()->create();

        expect($user->leagues())->toBeInstanceOf(HasMany::class);
        expect($user->leagues)->toHaveCount(2);
    });

    it('has many seasons', function () {
        $league = League::factory()->create();
        Season::factory()->count(3)->create(['league_id' => $league->id]);

        expect($league->seasons)->toHaveCount(3);
    });

    it('cascades season deletion when the league is deleted', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $league->delete();

        expect(Season::find($season->id))->toBeNull();
    });

    it('generates a unique slug from the name when none provided', function () {
        $first = League::factory()->create(['name' => 'My Awesome League', 'slug' => null]);
        $second = League::factory()->create(['name' => 'My Awesome League', 'slug' => null]);

        expect($first->slug)->toBe('my-awesome-league');
        expect($second->slug)->toBe('my-awesome-league-2');
    });

    it('uses the slug as the route key', function () {
        $league = League::factory()->create();

        expect($league->getRouteKeyName())->toBe('slug');
    });
});

describe('Season ↔ Team pivot', function () {
    it('attaches teams to a season', function () {
        $season = Season::factory()->create();
        $teams = Team::factory()->count(3)->create();

        $season->teams()->attach($teams);

        expect($season->teams)->toHaveCount(3);
        expect($season->teams()->first())->toBeInstanceOf(Team::class);
    });

    it('is reachable via Team::seasons()', function () {
        $season = Season::factory()->create();
        $team = Team::factory()->create();
        $season->teams()->attach($team);

        expect($team->seasons())->toBeInstanceOf(BelongsToMany::class);
        expect($team->seasons)->toHaveCount(1);
        expect($team->seasons->first()->id)->toBe($season->id);
    });

    it('enforces a unique (season_id, team_id) pair', function () {
        $season = Season::factory()->create();
        $team = Team::factory()->create();
        $season->teams()->attach($team);

        expect(fn () => $season->teams()->attach($team))
            ->toThrow(QueryException::class);
    });
});

describe('Games ↔ Season', function () {
    it('allows nullable season_id (legacy games)', function () {
        $game = Game::factory()->create();

        expect($game->season_id)->toBeNull();
        expect($game->season)->toBeNull();
    });

    it('associates a game with a season when provided', function () {
        $season = Season::factory()->create();
        $game = Game::factory()->create(['season_id' => $season->id]);

        expect($game->season)->toBeInstanceOf(Season::class);
        expect($season->games)->toHaveCount(1);
    });
});
