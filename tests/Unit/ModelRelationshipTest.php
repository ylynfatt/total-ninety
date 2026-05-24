<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Result;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;

describe('Team relationships', function () {
    it('has many games', function () {
        $team = Team::factory()->create();

        expect($team->games())->toBeInstanceOf(HasMany::class);
    });

    it('has many home games', function () {
        $team = Team::factory()->create();

        expect($team->homeGames())->toBeInstanceOf(HasMany::class);
    });

    it('has many away games', function () {
        $team = Team::factory()->create();

        expect($team->awayGames())->toBeInstanceOf(HasMany::class);
    });

    it('can retrieve home games correctly', function () {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $homeGame = Game::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        Game::factory()->create([
            'home_team_id' => $awayTeam->id,
            'away_team_id' => $homeTeam->id,
        ]);

        $homeGames = $homeTeam->homeGames;

        expect($homeGames)->toHaveCount(1);
        expect($homeGames->first()->id)->toBe($homeGame->id);
    });

    it('can retrieve away games correctly', function () {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        Game::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        $awayGame = Game::factory()->create([
            'home_team_id' => $awayTeam->id,
            'away_team_id' => $homeTeam->id,
        ]);

        $awayGames = $homeTeam->awayGames;

        expect($awayGames)->toHaveCount(1);
        expect($awayGames->first()->id)->toBe($awayGame->id);
    });
});

describe('Game relationships', function () {
    it('belongs to home team', function () {
        $game = Game::factory()->create();

        expect($game->homeTeam())->toBeInstanceOf(BelongsTo::class);
    });

    it('belongs to away team', function () {
        $game = Game::factory()->create();

        expect($game->awayTeam())->toBeInstanceOf(BelongsTo::class);
    });

    it('has one result', function () {
        $game = Game::factory()->create();

        expect($game->result())->toBeInstanceOf(HasOne::class);
    });

    it('can retrieve home and away teams correctly', function () {
        $homeTeam = Team::factory()->create(['name' => 'Home Team']);
        $awayTeam = Team::factory()->create(['name' => 'Away Team']);

        $game = Game::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        expect($game->homeTeam->name)->toBe('Home Team');
        expect($game->awayTeam->name)->toBe('Away Team');
    });
});

describe('Result relationships', function () {
    it('belongs to game', function () {
        $result = Result::factory()->create();

        expect($result->game())->toBeInstanceOf(BelongsTo::class);
    });

    it('can retrieve associated game', function () {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $game = Game::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'location' => 'Test Stadium',
        ]);

        $result = Result::factory()->create([
            'game_id' => $game->id,
            'home_team_score' => 2,
            'away_team_score' => 1,
        ]);

        expect($result->game->location)->toBe('Test Stadium');
        expect($result->home_team_score)->toBe(2);
        expect($result->away_team_score)->toBe(1);
    });
});

describe('Player relationships', function () {
    it('belongs to a team', function () {
        $player = Player::factory()->create();

        expect($player->team())->toBeInstanceOf(BelongsTo::class);
    });

    it('can be a free agent without a team', function () {
        $player = Player::factory()->freeAgent()->create();

        expect($player->team_id)->toBeNull();
        expect($player->team)->toBeNull();
    });

    it('is exposed via Team::players()', function () {
        $team = Team::factory()->create();
        Player::factory()->count(3)->create(['team_id' => $team->id]);

        expect($team->players())->toBeInstanceOf(HasMany::class);
        expect($team->players)->toHaveCount(3);
    });
});

describe('Foreign key constraints', function () {
    it('cascade-deletes a result when its game is deleted', function () {
        $game = Game::factory()->create();
        $result = Result::factory()->create(['game_id' => $game->id]);

        $game->delete();

        expect(Result::find($result->id))->toBeNull();
    });

    it('prevents deleting a team that still has games', function () {
        $team = Team::factory()->create();
        Game::factory()->create(['home_team_id' => $team->id]);

        expect(fn () => $team->delete())->toThrow(QueryException::class);
    });

    it('nulls a players team_id when its team is deleted', function () {
        $team = Team::factory()->create();
        $player = Player::factory()->create(['team_id' => $team->id]);

        $team->delete();

        expect($player->fresh()->team_id)->toBeNull();
    });
});
