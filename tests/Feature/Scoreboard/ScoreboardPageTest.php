<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Result;
use App\Models\Season;
use App\Models\Team;

describe('ScoreboardController index', function () {
    it('renders the scoreboard for unauthenticated visitors', function () {
        $this->get('/scoreboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Scoreboard/Index')
                ->has('games', 0)
            );
    });

    it('lists only games that are in progress', function () {
        $season = Season::factory()->create();

        Game::factory()->for($season)->live()->create();
        Game::factory()->for($season)->halfTime()->create();
        Game::factory()->for($season)->fullTime()->create();
        Game::factory()->for($season)->create(); // scheduled

        $this->get('/scoreboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Scoreboard/Index')
                ->has('games', 2)
            );
    });

    it('includes team names, score, status and league context', function () {
        $season = Season::factory()->create();
        $home = Team::factory()->create(['name' => 'Home United', 'acronym' => 'HOM']);
        $away = Team::factory()->create(['name' => 'Away City', 'acronym' => 'AWY']);

        $game = Game::factory()->for($season)->live(67)->create([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);

        Result::factory()->for($game)->create([
            'home_team_score' => 2,
            'away_team_score' => 1,
        ]);

        $this->get('/scoreboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Scoreboard/Index')
                ->has('games', 1)
                ->where('games.0.home_team.name', 'Home United')
                ->where('games.0.away_team.name', 'Away City')
                ->where('games.0.home_team_score', 2)
                ->where('games.0.away_team_score', 1)
                ->where('games.0.status', GameStatus::Live->value)
                ->where('games.0.status_label', 'Live')
                ->where('games.0.current_minute', 67)
                ->where('games.0.league_name', $season->league->name)
            );
    });

    it('returns null scores when a live game has no result yet', function () {
        $season = Season::factory()->create();

        Game::factory()->for($season)->live()->create();

        $this->get('/scoreboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('games.0.home_team_score', null)
                ->where('games.0.away_team_score', null)
            );
    });
});
