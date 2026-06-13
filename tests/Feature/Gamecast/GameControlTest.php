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

function eventUrl(League $league, Season $season, Stage $stage, Game $game, GameEvent $event): string
{
    return eventsUrl($league, $season, $stage, $game)."/{$event->id}";
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

describe('match clock', function () {
    it('starts the running clock on kick off', function () {
        [$owner, $league, $season, $stage, $game] = controlChain();

        $this->actingAs($owner)
            ->patch(statusUrl($league, $season, $stage, $game), [
                'status' => GameStatus::Live->value,
                'current_minute' => 0,
            ])
            ->assertRedirect();

        $game->refresh();
        expect($game->status)->toBe(GameStatus::Live)
            ->and($game->current_minute)->toBe(0)
            ->and($game->clock_started_at)->not->toBeNull();
    });

    it('freezes at the elapsed minute when paused for half time', function () {
        [$owner, $league, $season, $stage, $game] = controlChain();
        $this->freezeTime();

        $this->actingAs($owner)->patch(statusUrl($league, $season, $stage, $game), [
            'status' => GameStatus::Live->value,
            'current_minute' => 0,
        ]);

        $this->travel(47)->minutes();

        $this->actingAs($owner)->patch(statusUrl($league, $season, $stage, $game), [
            'status' => GameStatus::HalfTime->value,
        ])->assertRedirect();

        $game->refresh();
        expect($game->status)->toBe(GameStatus::HalfTime)
            ->and($game->current_minute)->toBe(47)
            ->and($game->clock_started_at)->toBeNull();
    });

    it('resumes from the frozen minute and restarts the clock', function () {
        [$owner, $league, $season, $stage, $game] = controlChain();
        $game->update(['status' => GameStatus::HalfTime, 'current_minute' => 45, 'clock_started_at' => null]);

        $this->actingAs($owner)->patch(statusUrl($league, $season, $stage, $game), [
            'status' => GameStatus::Live->value,
        ])->assertRedirect();

        $game->refresh();
        expect($game->status)->toBe(GameStatus::Live)
            ->and($game->current_minute)->toBe(45)
            ->and($game->clock_started_at)->not->toBeNull();
    });

    it('re-anchors a running clock to a manual minute correction', function () {
        [$owner, $league, $season, $stage, $game] = controlChain();
        $this->freezeTime();

        $this->actingAs($owner)->patch(statusUrl($league, $season, $stage, $game), [
            'status' => GameStatus::Live->value,
            'current_minute' => 30,
        ]);
        $startedAt = $game->refresh()->clock_started_at;

        $this->travel(10)->minutes();

        $this->actingAs($owner)->patch(statusUrl($league, $season, $stage, $game), [
            'status' => GameStatus::Live->value,
            'current_minute' => 42,
        ])->assertRedirect();

        $game->refresh();
        expect($game->current_minute)->toBe(42)
            ->and($game->clock_started_at->greaterThan($startedAt))->toBeTrue();
    });

    it('clears the clock when a game is postponed', function () {
        [$owner, $league, $season, $stage, $game] = controlChain();
        $this->actingAs($owner)->patch(statusUrl($league, $season, $stage, $game), [
            'status' => GameStatus::Live->value,
            'current_minute' => 0,
        ]);

        $this->actingAs($owner)->patch(statusUrl($league, $season, $stage, $game), [
            'status' => GameStatus::Postponed->value,
        ])->assertRedirect();

        expect($game->refresh()->clock_started_at)->toBeNull();
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

describe('updateEvent', function () {
    it('moves a goal to the other team and rebalances the scoreline', function () {
        [$owner, $league, $season, $stage, $game, $home, $away] = controlChain();
        Result::factory()->for($game)->create(['home_team_score' => 1, 'away_team_score' => 0]);
        $event = GameEvent::factory()->for($game)->create([
            'type' => GameEventType::Goal,
            'team_id' => $home->id,
        ]);

        $this->actingAs($owner)
            ->patch(eventUrl($league, $season, $stage, $game, $event), [
                'type' => GameEventType::Goal->value,
                'team_id' => $away->id,
            ])
            ->assertRedirect();

        expect($event->fresh()->team_id)->toBe($away->id);

        $result = Result::where('game_id', $game->id)->first();
        expect($result->home_team_score)->toBe(0)
            ->and($result->away_team_score)->toBe(1);
    });

    it('takes the point off when a goal is corrected to a non-scoring event', function () {
        [$owner, $league, $season, $stage, $game, $home] = controlChain();
        Result::factory()->for($game)->create(['home_team_score' => 2, 'away_team_score' => 0]);
        $event = GameEvent::factory()->for($game)->create([
            'type' => GameEventType::Goal,
            'team_id' => $home->id,
        ]);

        $this->actingAs($owner)
            ->patch(eventUrl($league, $season, $stage, $game, $event), [
                'type' => GameEventType::YellowCard->value,
                'team_id' => $home->id,
            ])
            ->assertRedirect();

        $result = Result::where('game_id', $game->id)->first();
        expect($result->home_team_score)->toBe(1)
            ->and($result->away_team_score)->toBe(0);
    });

    it('leaves the score untouched when editing only a detail', function () {
        [$owner, $league, $season, $stage, $game, $home] = controlChain();
        Result::factory()->for($game)->create(['home_team_score' => 1, 'away_team_score' => 0]);
        $event = GameEvent::factory()->for($game)->create([
            'type' => GameEventType::Goal,
            'team_id' => $home->id,
            'minute' => 10,
        ]);

        $this->actingAs($owner)
            ->patch(eventUrl($league, $season, $stage, $game, $event), [
                'type' => GameEventType::Goal->value,
                'team_id' => $home->id,
                'minute' => 42,
            ])
            ->assertRedirect();

        expect($event->fresh()->minute)->toBe(42);

        $result = Result::where('game_id', $game->id)->first();
        expect($result->home_team_score)->toBe(1)
            ->and($result->away_team_score)->toBe(0);
    });

    it('forbids non-owners', function () {
        [, $league, $season, $stage, $game, $home] = controlChain();
        $event = GameEvent::factory()->for($game)->create(['type' => GameEventType::Goal, 'team_id' => $home->id]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->patch(eventUrl($league, $season, $stage, $game, $event), [
                'type' => GameEventType::Goal->value,
                'team_id' => $home->id,
            ])
            ->assertForbidden();
    });

    it('404s when the event belongs to another game', function () {
        [$owner, $league, $season, $stage, $game] = controlChain();
        $otherGame = Game::factory()->for($season)->for($stage)->create();
        $event = GameEvent::factory()->for($otherGame)->create();

        $this->actingAs($owner)
            ->patch(eventUrl($league, $season, $stage, $game, $event), [
                'type' => GameEventType::Commentary->value,
            ])
            ->assertNotFound();
    });
});

describe('destroyEvent', function () {
    it('removes a goal and walks back the scoreline', function () {
        [$owner, $league, $season, $stage, $game, $home] = controlChain();
        Result::factory()->for($game)->create(['home_team_score' => 2, 'away_team_score' => 1]);
        $event = GameEvent::factory()->for($game)->create([
            'type' => GameEventType::Goal,
            'team_id' => $home->id,
        ]);

        $this->actingAs($owner)
            ->delete(eventUrl($league, $season, $stage, $game, $event))
            ->assertRedirect();

        expect(GameEvent::find($event->id))->toBeNull();

        $result = Result::where('game_id', $game->id)->first();
        expect($result->home_team_score)->toBe(1)
            ->and($result->away_team_score)->toBe(1);
    });

    it('removes a non-scoring event without touching the score', function () {
        [$owner, $league, $season, $stage, $game] = controlChain();
        $event = GameEvent::factory()->for($game)->create(['type' => GameEventType::Commentary, 'team_id' => null]);

        $this->actingAs($owner)
            ->delete(eventUrl($league, $season, $stage, $game, $event))
            ->assertRedirect();

        expect(GameEvent::find($event->id))->toBeNull()
            ->and(Result::where('game_id', $game->id)->exists())->toBeFalse();
    });

    it('forbids non-owners', function () {
        [, $league, $season, $stage, $game, $home] = controlChain();
        $event = GameEvent::factory()->for($game)->create(['type' => GameEventType::Goal, 'team_id' => $home->id]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->delete(eventUrl($league, $season, $stage, $game, $event))
            ->assertForbidden();
    });
});
