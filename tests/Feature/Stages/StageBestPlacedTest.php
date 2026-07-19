<?php

use App\Enums\StageFormat;
use App\Models\Game;
use App\Models\Group;
use App\Models\League;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;

/**
 * Build a public-league GroupStage with two groups of three named teams and
 * a decided set of games so each group has a distinct 3rd place:
 * "Alpha Third" (3 pts) in Group A and "Beta Third" (0 pts) in Group B.
 *
 * @param  array<string, mixed>  $stageAttributes
 * @return array{0: League, 1: Season, 2: Stage}
 */
function bestPlacedStageScaffold(array $stageAttributes = []): array
{
    $league = League::factory()->create(['is_public' => true]);
    $season = Season::factory()->create(['league_id' => $league->id]);
    $stage = Stage::factory()->groupStage()->create([
        'season_id' => $season->id,
        ...$stageAttributes,
    ]);

    $rosters = [
        'Group A' => ['Alpha First', 'Alpha Second', 'Alpha Third'],
        'Group B' => ['Beta First', 'Beta Second', 'Beta Third'],
    ];

    $teams = [];
    foreach ($rosters as $groupName => $teamNames) {
        $group = Group::factory()->create(['stage_id' => $stage->id, 'name' => $groupName]);

        foreach ($teamNames as $teamName) {
            $team = Team::factory()->create(['name' => $teamName]);
            $season->teams()->attach($team);
            $group->teams()->attach($team);
            $teams[$teamName] = ['team' => $team, 'group' => $group];
        }
    }

    $play = function (string $home, string $away, int $hs, int $as) use ($stage, $teams): void {
        $game = Game::factory()->create([
            'stage_id' => $stage->id,
            'season_id' => $stage->season_id,
            'group_id' => $teams[$home]['group']->id,
            'home_team_id' => $teams[$home]['team']->id,
            'away_team_id' => $teams[$away]['team']->id,
            'match_date' => now()->subDay(),
        ]);

        Result::factory()->create([
            'game_id' => $game->id,
            'home_team_score' => $hs,
            'away_team_score' => $as,
        ]);
    };

    // Group A: First 6 pts, Second 3 pts (h2h over Third), Third 3 pts.
    $play('Alpha First', 'Alpha Second', 1, 0);
    $play('Alpha First', 'Alpha Third', 2, 0);
    $play('Alpha Third', 'Alpha Second', 1, 0);
    $play('Alpha Second', 'Alpha Third', 2, 0);

    // Group B: First 6 pts, Second 3 pts, Third 0 pts.
    $play('Beta First', 'Beta Second', 1, 0);
    $play('Beta First', 'Beta Third', 2, 0);
    $play('Beta Second', 'Beta Third', 1, 0);

    return [$league, $season, $stage];
}

describe('Stage show bestPlaced prop', function () {
    it('ranks best third-placed teams across groups when best_placed_count is configured', function () {
        [$league, $season, $stage] = bestPlacedStageScaffold([
            'config' => ['best_placed_count' => 1],
        ]);

        $this->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('bestPlaced.position', 3)
                ->where('bestPlaced.qualify_count', 1)
                ->has('bestPlaced.rows', 2)
                ->where('bestPlaced.rows.0.team_name', 'Alpha Third')
                ->where('bestPlaced.rows.0.group_name', 'Group A')
                ->where('bestPlaced.rows.0.points', 3)
                ->where('bestPlaced.rows.1.team_name', 'Beta Third')
                ->where('bestPlaced.rows.1.points', 0)
            );
    });

    it('derives the ranked position from advances_count', function () {
        [$league, $season, $stage] = bestPlacedStageScaffold([
            'advances_count' => 1,
            'config' => ['best_placed_count' => 1],
        ]);

        $this->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('bestPlaced.position', 2)
                ->where('bestPlaced.rows.0.team_name', 'Alpha Second')
            );
    });

    it('omits the ranking when best_placed_count is not configured', function () {
        [$league, $season, $stage] = bestPlacedStageScaffold();

        $this->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('bestPlaced', null));
    });
});

describe('Stage config.best_placed_count validation', function () {
    it('accepts best_placed_count for a group stage', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'Group Stage',
                'format' => StageFormat::GroupStage->value,
                'config' => ['best_placed_count' => '8'],
            ])
            ->assertRedirect();

        expect(Stage::where('name', 'Group Stage')->firstOrFail()->config)
            ->toBe(['best_placed_count' => 8]);
    });

    it('silently drops best_placed_count for ungrouped formats', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'Regular Season',
                'format' => StageFormat::RoundRobinSingle->value,
                'config' => ['best_placed_count' => 4],
            ])
            ->assertRedirect();

        expect(Stage::where('name', 'Regular Season')->firstOrFail()->config)->toBeNull();
    });

    it('rejects a best_placed_count above 16', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'Group Stage',
                'format' => StageFormat::GroupStage->value,
                'config' => ['best_placed_count' => 17],
            ])
            ->assertSessionHasErrors('config.best_placed_count');
    });

    it('updates best_placed_count on an existing group stage', function () {
        [$league, $season, $stage] = bestPlacedStageScaffold();

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$stage->id}", [
                'name' => $stage->name,
                'config' => ['best_placed_count' => 4],
            ])
            ->assertRedirect();

        expect($stage->fresh()->config['best_placed_count'])->toBe(4);
    });
});
