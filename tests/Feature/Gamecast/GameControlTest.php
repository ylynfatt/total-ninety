<?php

use App\Enums\GameEventType;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\League;
use App\Models\Player;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;
use App\Models\User;

function controlChain(?User $owner = null): array
{
    $owner ??= User::factory()->create();
    $league = League::factory()->create(['user_id' => $owner->id]);
    $season = Season::factory()->for($league)->create();
    $stage = Stage::factory()->for($season)->create();
    $home = Team::factory()->create();
    $away = Team::factory()->create();
    $game = Game::factory()->for($season)->for($stage)->create([
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
    ]);

    return [$owner, $league, $season, $stage, $game, $home, $away];
}

function statusUrl(League $league, Season $season, Stage $stage, Game $game): string
{
    return "/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/status";
}

function eventsUrl(League $league, Season $season, Stage $stage, Game $game): string
{
    return "/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}/events";
}

describe('updateStatus', function () {
    it('lets the owner change the game status and minute', function () {
        [$owner, $league, $season, $stage, $game] = controlChain();

        $this->actingAs($owner)
            ->patch(statusUrl($league, $season, $stage, $game), [
                'status' => GameStatus::Live->value,
                'current_minute' => 1,
            ])
            ->assertRedirect();

        expect($game->fresh()->status)->toBe(GameStatus::Live)
            ->and($game->fresh()->current_minute)->toBe(1);
    });

    it('forbids non-owners', function () {
        [, $league, $season, $stage, $game] = controlChain();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->patch(statusUrl($league, $season, $stage, $game), ['status' => GameStatus::Live->value])
            ->assertForbidden();
    });

    it('requires authentication', function () {
        [, $league, $season, $stage, $game] = controlChain();

        $this->patch(statusUrl($league, $season, $stage, $game), ['status' => GameStatus::Live->value])
            ->assertRedirect('/login');
    });

    it('validates the status value', function () {
        [$owner, $league, $season, $stage, $game] = controlChain();

        $this->actingAs($owner)
            ->patch(statusUrl($league, $season, $stage, $game), ['status' => 'not_a_status'])
            ->assertSessionHasErrors('status');
    });
});

describe('storeEvent', function () {
    it('records a goal and increments the scoring team', function () {
        [$owner, $league, $season, $stage, $game, $home] = controlChain();
        $scorer = Player::factory()->for($home)->create();

        $this->actingAs($owner)
            ->post(eventsUrl($league, $season, $stage, $game), [
                'type' => GameEventType::Goal->value,
                'minute' => 23,
                'team_id' => $home->id,
                'player_id' => $scorer->id,
            ])
            ->assertRedirect();

        expect(GameEvent::where('game_id', $game->id)->count())->toBe(1);

        $result = Result::where('game_id', $game->id)->first();
        expect($result->home_team_score)->toBe(1)
            ->and($result->away_team_score)->toBe(0);
    });

    it('credits the opponent for an own goal', function () {
        [$owner, $league, $season, $stage, $game, $home] = controlChain();

        $this->actingAs($owner)
            ->post(eventsUrl($league, $season, $stage, $game), [
                'type' => GameEventType::OwnGoal->value,
                'minute' => 30,
                'team_id' => $home->id,
            ])
            ->assertRedirect();

        $result = Result::where('game_id', $game->id)->first();
        expect($result->home_team_score)->toBe(0)
            ->and($result->away_team_score)->toBe(1);
    });

    it('adds to an existing scoreline', function () {
        [$owner, $league, $season, $stage, $game, $home] = controlChain();
        Result::factory()->for($game)->create(['home_team_score' => 2, 'away_team_score' => 1]);

        $this->actingAs($owner)
            ->post(eventsUrl($league, $season, $stage, $game), [
                'type' => GameEventType::PenaltyGoal->value,
                'team_id' => $home->id,
            ])
            ->assertRedirect();

        $result = Result::where('game_id', $game->id)->first();
        expect($result->home_team_score)->toBe(3)
            ->and($result->away_team_score)->toBe(1);
    });

    it('records a non-scoring event without touching the score', function () {
        [$owner, $league, $season, $stage, $game] = controlChain();

        $this->actingAs($owner)
            ->post(eventsUrl($league, $season, $stage, $game), [
                'type' => GameEventType::Commentary->value,
                'minute' => 10,
                'description' => 'Bright start.',
            ])
            ->assertRedirect();

        expect(GameEvent::where('game_id', $game->id)->count())->toBe(1)
            ->and(Result::where('game_id', $game->id)->exists())->toBeFalse();
    });

    it('requires a team for scoring events', function () {
        [$owner, $league, $season, $stage, $game] = controlChain();

        $this->actingAs($owner)
            ->post(eventsUrl($league, $season, $stage, $game), [
                'type' => GameEventType::Goal->value,
            ])
            ->assertSessionHasErrors('team_id');
    });

    it('forbids non-owners', function () {
        [, $league, $season, $stage, $game, $home] = controlChain();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post(eventsUrl($league, $season, $stage, $game), [
                'type' => GameEventType::Goal->value,
                'team_id' => $home->id,
            ])
            ->assertForbidden();
    });
});
