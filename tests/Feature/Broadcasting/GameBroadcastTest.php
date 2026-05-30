<?php

use App\Enums\GameEventType;
use App\Enums\GameStatus;
use App\Events\GameEventRecorded;
use App\Events\GameStatusChanged;
use App\Events\ScoreUpdated;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Result;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Event;

describe('ScoreUpdated', function () {
    it('is dispatched when a result is created', function () {
        Event::fake([ScoreUpdated::class]);
        $game = Game::factory()->create();

        Result::factory()->create([
            'game_id' => $game->id,
            'home_team_score' => 2,
            'away_team_score' => 1,
        ]);

        Event::assertDispatched(ScoreUpdated::class, fn (ScoreUpdated $e) => $e->game->id === $game->id);
    });

    it('is dispatched when a result is updated', function () {
        $game = Game::factory()->create();
        $result = Result::factory()->create(['game_id' => $game->id]);

        Event::fake([ScoreUpdated::class]);
        $result->update(['home_team_score' => 5]);

        Event::assertDispatched(ScoreUpdated::class);
    });

    it('is dispatched when a result is cleared', function () {
        $game = Game::factory()->create();
        $result = Result::factory()->create(['game_id' => $game->id]);

        Event::fake([ScoreUpdated::class]);
        $result->delete();

        Event::assertDispatched(ScoreUpdated::class);
    });

    it('broadcasts on the game and scoreboard channels', function () {
        $game = Game::factory()->create(['status' => GameStatus::Live, 'current_minute' => 57]);
        $result = Result::factory()->create([
            'game_id' => $game->id,
            'home_team_score' => 2,
            'away_team_score' => 1,
        ]);
        $game->setRelation('result', $result);

        $channels = collect((new ScoreUpdated($game))->broadcastOn())
            ->map(fn (Channel $c) => $c->name)
            ->all();

        expect($channels)->toContain("game.{$game->id}");
        expect($channels)->toContain('scoreboard.live');
    });

    it('carries the score, minute and status in its payload', function () {
        $game = Game::factory()->create(['status' => GameStatus::Live, 'current_minute' => 57]);
        $result = Result::factory()->create([
            'game_id' => $game->id,
            'home_team_score' => 2,
            'away_team_score' => 1,
        ]);
        $game->setRelation('result', $result);

        $payload = (new ScoreUpdated($game))->broadcastWith();

        expect($payload)->toMatchArray([
            'game_id' => $game->id,
            'home_team_score' => 2,
            'away_team_score' => 1,
            'current_minute' => 57,
            'status' => 'live',
        ]);
    });
});

describe('GameEventRecorded', function () {
    it('is dispatched when a game event is created', function () {
        Event::fake([GameEventRecorded::class]);
        $game = Game::factory()->create();

        $event = GameEvent::factory()->goal()->create(['game_id' => $game->id]);

        Event::assertDispatched(
            GameEventRecorded::class,
            fn (GameEventRecorded $e) => $e->event->id === $event->id,
        );
    });

    it('broadcasts on the game channel only', function () {
        $game = Game::factory()->create();
        $event = GameEvent::factory()->create(['game_id' => $game->id]);

        $channels = collect((new GameEventRecorded($event))->broadcastOn())
            ->map(fn (Channel $c) => $c->name)
            ->all();

        expect($channels)->toBe(["game.{$game->id}"]);
    });

    it('carries the event shape in its payload', function () {
        $game = Game::factory()->create();
        $event = GameEvent::factory()->create([
            'game_id' => $game->id,
            'minute' => 23,
            'type' => GameEventType::YellowCard,
            'description' => 'Late challenge',
        ]);

        $payload = (new GameEventRecorded($event))->broadcastWith();

        expect($payload)->toMatchArray([
            'id' => $event->id,
            'game_id' => $game->id,
            'minute' => 23,
            'type' => 'yellow_card',
            'description' => 'Late challenge',
        ]);
    });
});

describe('GameStatusChanged', function () {
    it('is dispatched when the status changes', function () {
        $game = Game::factory()->create(['status' => GameStatus::Scheduled]);

        Event::fake([GameStatusChanged::class]);
        $game->update(['status' => GameStatus::Live]);

        Event::assertDispatched(
            GameStatusChanged::class,
            fn (GameStatusChanged $e) => $e->game->id === $game->id,
        );
    });

    it('is NOT dispatched when a non-status field changes', function () {
        $game = Game::factory()->create(['status' => GameStatus::Live]);

        Event::fake([GameStatusChanged::class]);
        // A plain schedule edit — should not masquerade as a status transition.
        $game->update(['location' => 'A Different Stadium']);

        Event::assertNotDispatched(GameStatusChanged::class);
    });

    it('broadcasts on the game and scoreboard channels', function () {
        $game = Game::factory()->create(['status' => GameStatus::Live, 'current_minute' => 12]);

        $channels = collect((new GameStatusChanged($game))->broadcastOn())
            ->map(fn (Channel $c) => $c->name)
            ->all();

        expect($channels)->toContain("game.{$game->id}");
        expect($channels)->toContain('scoreboard.live');
    });

    it('carries the status and minute in its payload', function () {
        $game = Game::factory()->create(['status' => GameStatus::HalfTime, 'current_minute' => 45]);

        $payload = (new GameStatusChanged($game))->broadcastWith();

        expect($payload)->toMatchArray([
            'game_id' => $game->id,
            'status' => 'half_time',
            'current_minute' => 45,
        ]);
    });
});
