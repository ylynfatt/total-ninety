<?php

use App\Enums\GameEventType;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

describe('GameStatus enum', function () {
    it('classifies in-progress correctly', function () {
        expect(GameStatus::Live->isInProgress())->toBeTrue();
        expect(GameStatus::HalfTime->isInProgress())->toBeTrue();

        expect(GameStatus::Scheduled->isInProgress())->toBeFalse();
        expect(GameStatus::FullTime->isInProgress())->toBeFalse();
        expect(GameStatus::Postponed->isInProgress())->toBeFalse();
        expect(GameStatus::Cancelled->isInProgress())->toBeFalse();
    });

    it('classifies final correctly', function () {
        expect(GameStatus::FullTime->isFinal())->toBeTrue();
        expect(GameStatus::Cancelled->isFinal())->toBeTrue();

        expect(GameStatus::Scheduled->isFinal())->toBeFalse();
        expect(GameStatus::Live->isFinal())->toBeFalse();
        expect(GameStatus::HalfTime->isFinal())->toBeFalse();
        expect(GameStatus::Postponed->isFinal())->toBeFalse();
    });

    it('returns a human label for every case', function () {
        foreach (GameStatus::cases() as $status) {
            expect($status->label())->toBeString()->not->toBe('');
        }
    });
});

describe('GameEventType enum', function () {
    it('only the three goal variants count as scoring events', function () {
        expect(GameEventType::Goal->isScoringEvent())->toBeTrue();
        expect(GameEventType::OwnGoal->isScoringEvent())->toBeTrue();
        expect(GameEventType::PenaltyGoal->isScoringEvent())->toBeTrue();

        foreach ([GameEventType::YellowCard, GameEventType::RedCard, GameEventType::Substitution, GameEventType::Commentary] as $other) {
            expect($other->isScoringEvent())->toBeFalse();
        }
    });

    it('classifies lifecycle events (kick off / half time / full time)', function () {
        expect(GameEventType::KickOff->isLifecycleEvent())->toBeTrue();
        expect(GameEventType::HalfTime->isLifecycleEvent())->toBeTrue();
        expect(GameEventType::FullTime->isLifecycleEvent())->toBeTrue();

        expect(GameEventType::Goal->isLifecycleEvent())->toBeFalse();
        expect(GameEventType::YellowCard->isLifecycleEvent())->toBeFalse();
    });

    it('returns a human label for every case', function () {
        foreach (GameEventType::cases() as $type) {
            expect($type->label())->toBeString()->not->toBe('');
        }
    });
});

describe('Game status + current_minute', function () {
    it('defaults newly created games to Scheduled status with null current_minute', function () {
        $game = Game::factory()->create();

        expect($game->status)->toBe(GameStatus::Scheduled);
        expect($game->current_minute)->toBeNull();
    });

    it('round-trips the status as the enum', function () {
        $game = Game::factory()->create(['status' => GameStatus::Live, 'current_minute' => 67]);

        expect($game->fresh()->status)->toBe(GameStatus::Live);
        expect($game->fresh()->current_minute)->toBe(67);
    });
});

describe('GameEvent relationships', function () {
    it('belongs to a game', function () {
        $event = GameEvent::factory()->create();

        expect($event->game())->toBeInstanceOf(BelongsTo::class);
        expect($event->game)->toBeInstanceOf(Game::class);
    });

    it('is exposed via Game::events() ordered by minute then id', function () {
        $game = Game::factory()->create();
        $third = GameEvent::factory()->create(['game_id' => $game->id, 'minute' => 80]);
        $first = GameEvent::factory()->create(['game_id' => $game->id, 'minute' => 12]);
        $second = GameEvent::factory()->create(['game_id' => $game->id, 'minute' => 45]);

        expect($game->events())->toBeInstanceOf(HasMany::class);
        expect($game->events->pluck('id')->all())->toBe([$first->id, $second->id, $third->id]);
    });

    it('breaks ties at the same minute by id (insertion order)', function () {
        $game = Game::factory()->create();
        $earlier = GameEvent::factory()->create(['game_id' => $game->id, 'minute' => 45]);
        $later = GameEvent::factory()->create(['game_id' => $game->id, 'minute' => 45]);

        expect($game->events->pluck('id')->all())->toBe([$earlier->id, $later->id]);
    });

    it('cascade-deletes events when its game is deleted', function () {
        $game = Game::factory()->create();
        $event = GameEvent::factory()->create(['game_id' => $game->id]);

        $game->delete();

        expect(GameEvent::find($event->id))->toBeNull();
    });

    it('exposes optional team / player / assist / secondary relationships', function () {
        $team = Team::factory()->create();
        $scorer = Player::factory()->create();
        $assist = Player::factory()->create();
        $event = GameEvent::factory()->goal()->create([
            'team_id' => $team->id,
            'player_id' => $scorer->id,
            'assist_player_id' => $assist->id,
        ]);

        expect($event->team)->toBeInstanceOf(Team::class);
        expect($event->team->id)->toBe($team->id);
        expect($event->player->id)->toBe($scorer->id);
        expect($event->assistPlayer->id)->toBe($assist->id);
        expect($event->secondaryPlayer)->toBeNull();
    });

    it('models a substitution as a single row with both players', function () {
        $game = Game::factory()->create();
        $team = Team::factory()->create();
        $off = Player::factory()->create();
        $on = Player::factory()->create();

        $event = GameEvent::factory()->substitution()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'minute' => 60,
            'player_id' => $off->id,
            'secondary_player_id' => $on->id,
        ]);

        expect($event->type)->toBe(GameEventType::Substitution);
        expect($event->player->id)->toBe($off->id);
        expect($event->secondaryPlayer->id)->toBe($on->id);
    });

    it('nulls team_id when the referenced team is deleted but keeps the event row', function () {
        $team = Team::factory()->create();
        $game = Game::factory()->create();
        $event = GameEvent::factory()->create(['game_id' => $game->id, 'team_id' => $team->id]);

        // Detach the team from any seasons it might be in so the FK on games
        // doesn't restrict the delete.
        $team->seasons()->detach();
        // The game's home/away team refs are FK-restricted; force the team
        // to not be either by repointing the game's home_team_id first.
        $game->update(['home_team_id' => Team::factory()->create()->id, 'away_team_id' => Team::factory()->create()->id]);

        $team->delete();

        $fresh = $event->fresh();
        expect($fresh)->not->toBeNull();
        expect($fresh->team_id)->toBeNull();
    });
});
