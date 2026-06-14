<?php

use App\Enums\GameEventType;
use App\Models\Game;
use App\Models\GameEvent;

/**
 * Record events in the order they happened during a match, with the colliding
 * minutes that trip up a flat sort: the first half runs into stoppage (45+2)
 * before Half Time freezes at 49', then the second half kicks off back at 45'.
 */
function recordMatchTimeline(Game $game): void
{
    $entries = [
        ['type' => GameEventType::KickOff, 'minute' => null, 'stoppage' => null],
        ['type' => GameEventType::Goal, 'minute' => 30, 'stoppage' => null],
        ['type' => GameEventType::Goal, 'minute' => 45, 'stoppage' => 2],
        ['type' => GameEventType::HalfTime, 'minute' => 49, 'stoppage' => null],
        ['type' => GameEventType::KickOff, 'minute' => 45, 'stoppage' => null],
        ['type' => GameEventType::Goal, 'minute' => 60, 'stoppage' => null],
        ['type' => GameEventType::FullTime, 'minute' => 90, 'stoppage' => null],
    ];

    foreach ($entries as $entry) {
        GameEvent::factory()->create([
            'game_id' => $game->id,
            'type' => $entry['type'],
            'minute' => $entry['minute'],
            'stoppage' => $entry['stoppage'],
        ]);
    }
}

it('orders the second-half kick-off after half time, not before it', function () {
    $game = Game::factory()->create();
    recordMatchTimeline($game);

    $types = $game->load('events')
        ->timelineEvents()
        ->map(fn (GameEvent $event): string => $event->type->value)
        ->all();

    expect($types)->toBe([
        'kick_off',
        'goal',      // 30'
        'goal',      // 45+2'
        'half_time', // 49'
        'kick_off',  // second half, back to 45'
        'goal',      // 60'
        'full_time', // 90'
    ]);
});

it('keeps half time as the final event of the first half', function () {
    $game = Game::factory()->create();
    recordMatchTimeline($game);

    $ordered = $game->load('events')->timelineEvents()->values();

    $halfTimeIndex = $ordered->search(fn (GameEvent $e): bool => $e->type === GameEventType::HalfTime);
    $secondKickOff = $ordered->slice($halfTimeIndex + 1)->first();

    expect($secondKickOff->type)->toBe(GameEventType::KickOff)
        ->and($secondKickOff->minute)->toBe(45);
});

it('still sorts plainly by minute within a half', function () {
    $game = Game::factory()->create();
    // Recorded out of order; within the first half they should read 12' then 30'.
    GameEvent::factory()->create(['game_id' => $game->id, 'type' => GameEventType::KickOff, 'minute' => null]);
    GameEvent::factory()->goal()->create(['game_id' => $game->id, 'minute' => 30]);
    GameEvent::factory()->goal()->create(['game_id' => $game->id, 'minute' => 12]);

    $minutes = $game->load('events')
        ->timelineEvents()
        ->map(fn (GameEvent $event): ?int => $event->minute)
        ->all();

    expect($minutes)->toBe([null, 12, 30]);
});
