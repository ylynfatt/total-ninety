<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Result;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/**
 * Point the broadcaster at a connection that always throws, mirroring a Reverb
 * server being unreachable in development. ShouldBroadcastNow events fire
 * synchronously, so without graceful handling the failure would abort the
 * request that saved the data.
 */
beforeEach(function () {
    Broadcast::extend('throwing', fn () => new class implements Broadcaster
    {
        public function auth($request) {}

        public function validAuthenticationResponse($request, $result) {}

        public function broadcast(array $channels, $event, array $payload = []): void
        {
            throw new RuntimeException('Reverb is unreachable.');
        }
    });

    config(['broadcasting.default' => 'throwing']);
});

it('still saves a result when the broadcaster is unreachable', function () {
    Log::spy();

    $game = Game::factory()->create();

    $result = Result::factory()->for($game)->create([
        'home_team_score' => 2,
        'away_team_score' => 1,
    ]);

    expect(Result::find($result->id))->not->toBeNull()
        ->and($result->home_team_score)->toBe(2);

    Log::shouldHaveReceived('warning')->once();
});

it('still records a game event when the broadcaster is unreachable', function () {
    $game = Game::factory()->create();

    $event = GameEvent::factory()->for($game)->goal()->create();

    expect(GameEvent::find($event->id))->not->toBeNull();
});

it('still persists a status change when the broadcaster is unreachable', function () {
    $game = Game::factory()->create(['status' => GameStatus::Scheduled]);

    $game->update(['status' => GameStatus::Live]);

    expect($game->fresh()->status)->toBe(GameStatus::Live);
});
