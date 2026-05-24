<?php

use App\Models\Game;
use App\Models\League;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\User;
use App\Policies\GamePolicy;

describe('StagePolicy', function () {
    it('lets anyone view a stage inside a public league', function () {
        $league = League::factory()->create(['is_public' => true]);
        $season = Season::factory()->create(['league_id' => $league->id]);
        $stage = Stage::factory()->create(['season_id' => $season->id]);

        $stranger = User::factory()->create();

        expect($stranger->can('view', $stage))->toBeTrue();
    });

    it('hides a private leagues stage from non-owners', function () {
        $league = League::factory()->private()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);
        $stage = Stage::factory()->create(['season_id' => $season->id]);

        $stranger = User::factory()->create();

        expect($stranger->can('view', $stage))->toBeFalse();
        expect($league->owner->can('view', $stage))->toBeTrue();
    });

    it('only allows the league owner to update or delete a stage', function () {
        $stage = Stage::factory()->create();
        $stranger = User::factory()->create();

        expect($stranger->can('update', $stage))->toBeFalse();
        expect($stranger->can('delete', $stage))->toBeFalse();

        expect($stage->season->league->owner->can('update', $stage))->toBeTrue();
        expect($stage->season->league->owner->can('delete', $stage))->toBeTrue();
    });
});

describe('GamePolicy with the stage chain', function () {
    it('uses the stage chain when stage_id is present', function () {
        $stage = Stage::factory()->create();
        $game = Game::factory()->create(['stage_id' => $stage->id]);
        $stranger = User::factory()->create();

        expect($stranger->can('update', $game))->toBeFalse();
        expect($stage->season->league->owner->can('update', $game))->toBeTrue();
    });

    it('falls back to the season chain when only season_id is present', function () {
        $season = Season::factory()->create();
        $game = Game::factory()->create(['season_id' => $season->id, 'stage_id' => null]);
        $stranger = User::factory()->create();

        expect($stranger->can('update', $game))->toBeFalse();
        expect($season->league->owner->can('update', $game))->toBeTrue();
    });

    it('leaves fully legacy games (no stage, no season) mutable', function () {
        $game = Game::factory()->create();

        expect((new GamePolicy)->update(null, $game))->toBeTrue();
    });
});

describe('ResultPolicy with the stage chain', function () {
    it('uses the stage chain when its game has stage_id set', function () {
        $stage = Stage::factory()->create();
        $game = Game::factory()->create(['stage_id' => $stage->id]);
        $result = Result::factory()->create(['game_id' => $game->id]);
        $stranger = User::factory()->create();

        expect($stranger->can('update', $result))->toBeFalse();
        expect($stage->season->league->owner->can('update', $result))->toBeTrue();
    });
});
