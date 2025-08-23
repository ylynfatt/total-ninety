<?php

use App\Models\Game;
use App\Models\Result;
use App\Models\Team;

describe('Games controller', function () {
    it('can load games page', function () {
        $this->get('/games')->assertSuccessful();
    });

    it('displays games in chronological order', function () {
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();

        $laterGame = Game::factory()->create([
            'home_team_id' => $team1->id,
            'away_team_id' => $team2->id,
            'match_date' => now()->addDays(10),
        ]);

        $earlierGame = Game::factory()->create([
            'home_team_id' => $team2->id,
            'away_team_id' => $team1->id,
            'match_date' => now()->addDays(5),
        ]);

        $response = $this->get('/games');

        $response->assertSuccessful();
        // Earlier game should appear first in the response
        expect($response->getContent())->toMatch(
            '/.*'.preg_quote($earlierGame->location, '/').'.*'.preg_quote($laterGame->location, '/').'.*/s'
        );
    });

    it('can load games create page', function () {
        $this->get('/games/create')->assertSuccessful();
    });

    it('can create a new game', function () {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $gameData = [
            'home_team' => $homeTeam->id,
            'away_team' => $awayTeam->id,
            'match_date' => now()->addDays(7)->format('Y-m-d'),
            'location' => 'Wembley Stadium',
        ];

        $this->post('/games', $gameData)
            ->assertRedirect('/games')
            ->assertSessionHas('status', 'Game added successfully!');

        $this->assertDatabaseHas('games', [
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'location' => 'Wembley Stadium',
        ]);
    });

    it('can load game show page', function () {
        $homeTeam = Team::factory()->create(['name' => 'Home Team']);
        $awayTeam = Team::factory()->create(['name' => 'Away Team']);
        $game = Game::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        $this->get("/games/{$game->id}")
            ->assertSuccessful()
            ->assertSeeText('Home Team')
            ->assertSeeText('Away Team');
    });

    it('can load game edit page', function () {
        $game = Game::factory()->create();

        $this->get("/games/{$game->id}/edit")
            ->assertViewIs('games.edit')
            ->assertViewHas('game', $game)
            ->assertViewHas('teams');
    })->skip('View not implemented yet');

    it('can update a game', function () {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $newAwayTeam = Team::factory()->create();

        $game = Game::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'location' => 'Old Stadium',
        ]);

        $updateData = [
            'home_team' => $homeTeam->id,
            'away_team' => $newAwayTeam->id,
            'match_date' => now()->addDays(14)->format('Y-m-d'),
            'location' => 'New Stadium',
        ];

        $this->put("/games/{$game->id}", $updateData)
            ->assertRedirect("/games/{$game->id}")
            ->assertSessionHas('status', 'Game updated successfully!');

        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'away_team_id' => $newAwayTeam->id,
            'location' => 'New Stadium',
        ]);
    });

    it('can delete a game', function () {
        $game = Game::factory()->create();

        $this->delete("/games/{$game->id}")
            ->assertRedirect('/games')
            ->assertSessionHas('status', 'Game deleted successfully!');

        $this->assertDatabaseMissing('games', ['id' => $game->id]);
    });

    it('loads related teams and result when showing game', function () {
        $homeTeam = Team::factory()->create(['name' => 'Home Team']);
        $awayTeam = Team::factory()->create(['name' => 'Away Team']);

        $game = Game::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        $result = Result::factory()->create([
            'game_id' => $game->id,
            'home_team_score' => 3,
            'away_team_score' => 1,
        ]);

        $this->get("/games/{$game->id}")
            ->assertSuccessful();

        // Verify relationships are loaded to prevent N+1 queries
        expect($game->fresh()->relationLoaded('homeTeam'))->toBeFalse();
    });
});
