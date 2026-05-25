<?php

use App\Models\Game;
use App\Models\League;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;
use App\Models\User;

/**
 * Build league + season + stage + a single fixture (game) with two
 * different teams. Returns [league, season, stage, game].
 *
 * @return array{0: League, 1: Season, 2: Stage, 3: Game}
 */
function stageFixture(): array
{
    $league = League::factory()->create();
    $season = Season::factory()->create(['league_id' => $league->id]);
    $stage = Stage::factory()->create(['season_id' => $season->id]);

    $game = Game::factory()->create([
        'stage_id' => $stage->id,
        'season_id' => $season->id,
        'home_team_id' => Team::factory()->create()->id,
        'away_team_id' => Team::factory()->create()->id,
    ]);

    return [$league, $season, $stage, $game];
}

describe('GameFixturesController edit', function () {
    it('renders the edit page for the league owner', function () {
        [$league, $season, $stage, $game] = stageFixture();

        $this->actingAs($league->owner)
            ->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Games/Edit')
                ->where('game.id', $game->id)
            );
    });

    it('forbids non-owners from accessing the edit page', function () {
        [$league, $season, $stage, $game] = stageFixture();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/edit")
            ->assertForbidden();
    });

    it('404s when the game does not belong to the stage in the URL', function () {
        [$leagueA, $seasonA, $stageA] = stageFixture();
        [$leagueB, $seasonB, $stageB, $gameInB] = stageFixture();

        $this->actingAs($leagueA->owner)
            ->get("/leagues/{$leagueA->slug}/seasons/{$seasonA->id}/stages/{$stageA->id}/games/{$gameInB->id}/edit")
            ->assertNotFound();
    });
});

describe('GameFixturesController updateSchedule', function () {
    it('lets the owner set match_date + location', function () {
        [$league, $season, $stage, $game] = stageFixture();

        $this->actingAs($league->owner)
            ->patch("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/schedule", [
                'match_date' => '2026-08-15',
                'location' => 'Wembley Stadium',
            ])
            ->assertRedirect();

        $fresh = $game->fresh();
        expect($fresh->match_date->format('Y-m-d'))->toBe('2026-08-15');
        expect($fresh->location)->toBe('Wembley Stadium');
    });

    it('allows clearing date + location by sending nulls', function () {
        [$league, $season, $stage, $game] = stageFixture();
        $game->update(['match_date' => '2026-08-15', 'location' => 'Old Stadium']);

        $this->actingAs($league->owner)
            ->patch("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/schedule", [
                'match_date' => null,
                'location' => null,
            ])
            ->assertRedirect();

        $fresh = $game->fresh();
        expect($fresh->match_date)->toBeNull();
        expect($fresh->location)->toBeNull();
    });

    it('forbids non-owners from updating the schedule', function () {
        [$league, $season, $stage, $game] = stageFixture();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->patch("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/schedule", [
                'match_date' => '2026-08-15',
            ])
            ->assertForbidden();
    });
});

describe('GameFixturesController storeResult', function () {
    it('records a result for a previously unrecorded game', function () {
        [$league, $season, $stage, $game] = stageFixture();

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/result", [
                'home_team_score' => 2,
                'away_team_score' => 1,
            ])
            ->assertRedirect();

        expect($game->fresh()->result)->not->toBeNull();
        expect($game->fresh()->result->home_team_score)->toBe(2);
        expect($game->fresh()->result->away_team_score)->toBe(1);
    });

    it('updates an existing result without creating a second row', function () {
        [$league, $season, $stage, $game] = stageFixture();
        Result::factory()->create([
            'game_id' => $game->id,
            'home_team_score' => 1,
            'away_team_score' => 0,
        ]);

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/result", [
                'home_team_score' => 3,
                'away_team_score' => 2,
            ])
            ->assertRedirect();

        expect(Result::where('game_id', $game->id)->count())->toBe(1);
        expect($game->fresh()->result->home_team_score)->toBe(3);
    });

    it('rejects negative scores', function () {
        [$league, $season, $stage, $game] = stageFixture();

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/edit")
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/result", [
                'home_team_score' => -1,
                'away_team_score' => 0,
            ])
            ->assertSessionHasErrors('home_team_score');
    });

    it('rejects non-numeric scores', function () {
        [$league, $season, $stage, $game] = stageFixture();

        $this->actingAs($league->owner)
            ->from("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/edit")
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/result", [
                'home_team_score' => 'three',
                'away_team_score' => 1,
            ])
            ->assertSessionHasErrors('home_team_score');
    });

    it('forbids non-owners from recording a result', function () {
        [$league, $season, $stage, $game] = stageFixture();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/result", [
                'home_team_score' => 2,
                'away_team_score' => 1,
            ])
            ->assertForbidden();
    });
});

describe('GameFixturesController destroyResult', function () {
    it('clears a recorded result', function () {
        [$league, $season, $stage, $game] = stageFixture();
        Result::factory()->create(['game_id' => $game->id]);

        $this->actingAs($league->owner)
            ->delete("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/result")
            ->assertRedirect();

        expect($game->fresh()->result)->toBeNull();
    });

    it('is idempotent when there is no result to clear', function () {
        [$league, $season, $stage, $game] = stageFixture();

        $this->actingAs($league->owner)
            ->delete("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/result")
            ->assertRedirect();

        expect($game->fresh()->result)->toBeNull();
    });

    it('forbids non-owners from clearing a result', function () {
        [$league, $season, $stage, $game] = stageFixture();
        Result::factory()->create(['game_id' => $game->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->delete("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/result")
            ->assertForbidden();

        expect($game->fresh()->result)->not->toBeNull();
    });
});
