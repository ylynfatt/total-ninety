<?php

use App\Models\Game;
use App\Models\Result;
use App\Models\Team;

describe('Result model', function () {
    it('can be created with valid data', function () {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $game = Game::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        $result = Result::factory()->create([
            'game_id' => $game->id,
            'home_team_score' => 3,
            'away_team_score' => 1,
        ]);

        expect($result->home_team_score)->toBe(3);
        expect($result->away_team_score)->toBe(1);
        expect($result->game_id)->toBe($game->id);
    });

    it('casts scores to integers', function () {
        $result = Result::factory()->create([
            'home_team_score' => '2',
            'away_team_score' => '0',
        ]);

        expect($result->home_team_score)->toBeInt();
        expect($result->away_team_score)->toBeInt();
        expect($result->home_team_score)->toBe(2);
        expect($result->away_team_score)->toBe(0);
    });

    it('belongs to a game', function () {
        $game = Game::factory()->create();
        $result = Result::factory()->create(['game_id' => $game->id]);

        expect($result->game)->toBeInstanceOf(Game::class);
        expect($result->game->id)->toBe($game->id);
    });

    it('can determine winner', function () {
        $homeWin = Result::factory()->create([
            'home_team_score' => 3,
            'away_team_score' => 1,
        ]);

        $awayWin = Result::factory()->create([
            'home_team_score' => 1,
            'away_team_score' => 2,
        ]);

        $draw = Result::factory()->create([
            'home_team_score' => 2,
            'away_team_score' => 2,
        ]);

        expect($homeWin->home_team_score > $homeWin->away_team_score)->toBeTrue();
        expect($awayWin->away_team_score > $awayWin->home_team_score)->toBeTrue();
        expect($draw->home_team_score === $draw->away_team_score)->toBeTrue();
    });
});
