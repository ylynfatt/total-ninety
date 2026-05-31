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

function gamecastChain(array $gameAttributes = []): array
{
    $league = League::factory()->create();
    $season = Season::factory()->for($league)->create();
    $stage = Stage::factory()->for($season)->create();
    $game = Game::factory()->for($season)->for($stage)->create($gameAttributes);

    return [$league, $season, $stage, $game];
}

function gamecastUrl(League $league, Season $season, Stage $stage, Game $game): string
{
    return "/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}/games/{$game->id}";
}

describe('GamecastController show', function () {
    it('renders the gamecast for unauthenticated visitors', function () {
        [$league, $season, $stage, $game] = gamecastChain();

        $this->get(gamecastUrl($league, $season, $stage, $game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Gamecast/Show')
                ->where('game.id', $game->id)
                ->has('events', 0)
            );
    });

    it('includes the live score and status', function () {
        [$league, $season, $stage, $game] = gamecastChain([
            'status' => GameStatus::Live,
            'current_minute' => 55,
        ]);

        Result::factory()->for($game)->create([
            'home_team_score' => 3,
            'away_team_score' => 2,
        ]);

        $this->get(gamecastUrl($league, $season, $stage, $game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('game.status', GameStatus::Live->value)
                ->where('game.status_label', 'Live')
                ->where('game.current_minute', 55)
                ->where('game.home_team_score', 3)
                ->where('game.away_team_score', 2)
            );
    });

    it('returns events ordered by minute with resolved names', function () {
        [$league, $season, $stage, $game] = gamecastChain();
        $team = Team::factory()->create(['acronym' => 'HOM']);
        $scorer = Player::factory()->for($team)->create(['first_name' => 'Alex', 'last_name' => 'Striker']);

        GameEvent::factory()->for($game)->create([
            'type' => GameEventType::Commentary,
            'minute' => 40,
            'description' => 'Chance!',
        ]);

        GameEvent::factory()->for($game)->goal()->create([
            'minute' => 12,
            'team_id' => $team->id,
            'player_id' => $scorer->id,
        ]);

        $this->get(gamecastUrl($league, $season, $stage, $game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('events', 2)
                ->where('events.0.minute', 12)
                ->where('events.0.type', GameEventType::Goal->value)
                ->where('events.0.type_label', 'Goal')
                ->where('events.0.is_scoring', true)
                ->where('events.0.team_acronym', 'HOM')
                ->where('events.0.player_name', 'Alex Striker')
                ->where('events.1.minute', 40)
            );
    });

    it('tags each event with the side it belongs to', function () {
        $league = League::factory()->create();
        $season = Season::factory()->for($league)->create();
        $stage = Stage::factory()->for($season)->create();
        $home = Team::factory()->create();
        $away = Team::factory()->create();
        $game = Game::factory()->for($season)->for($stage)->create([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);

        GameEvent::factory()->for($game)->goal()->create(['minute' => 10, 'team_id' => $home->id]);
        GameEvent::factory()->for($game)->goal()->create(['minute' => 20, 'team_id' => $away->id]);
        GameEvent::factory()->for($game)->create(['type' => GameEventType::HalfTime, 'minute' => 45, 'team_id' => null]);

        $this->get(gamecastUrl($league, $season, $stage, $game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('events.0.side', 'home')
                ->where('events.1.side', 'away')
                ->where('events.2.side', null)
            );
    });

    it('404s when the game does not belong to the stage chain', function () {
        [$league, $season, $stage] = gamecastChain();
        $otherGame = Game::factory()->create();

        $this->get(gamecastUrl($league, $season, $stage, $otherGame))
            ->assertNotFound();
    });
});
