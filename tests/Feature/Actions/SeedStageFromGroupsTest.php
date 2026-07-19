<?php

use App\Actions\GenerateFixtures;
use App\Actions\SeedStageFromGroups;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Group;
use App\Models\League;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;
use App\Models\User;

/**
 * Build a completed two-group stage plus a knockout stage with generated
 * TBD fixtures, wired by entrant descriptors mixing group positions and
 * best-placed ranks.
 *
 * Standings by design (advances_count = 1, so best-placed position = 2):
 *   Group A: Alpha One (6 pts) > Alpha Two (3 pts, GD −1) > Alpha Three
 *   Group B: Beta One (6 pts) > Beta Two (3 pts, GD +3) > Beta Three
 *   Best-placed: #1 Beta Two, #2 Alpha Two
 *
 * Entrants: [Winner A, Best #2, Winner B, Best #1]
 *   → Match 1: Alpha One v Alpha Two, Match 2: Beta One v Beta Two.
 *
 * @return array{0: League, 1: Season, 2: Stage, 3: Stage, 4: array<string, Team>}
 */
function seededKnockoutScaffold(): array
{
    $league = League::factory()->create(['is_public' => true]);
    $season = Season::factory()->create(['league_id' => $league->id]);

    $groupStage = Stage::factory()->groupStage()->create([
        'season_id' => $season->id,
        'name' => 'Groups',
        'order' => 10,
        'advances_count' => 1,
    ]);

    $teams = [];
    $rosters = [
        'Group A' => ['Alpha One', 'Alpha Two', 'Alpha Three'],
        'Group B' => ['Beta One', 'Beta Two', 'Beta Three'],
    ];

    foreach ($rosters as $groupName => $names) {
        $group = Group::factory()->create(['stage_id' => $groupStage->id, 'name' => $groupName]);

        foreach ($names as $name) {
            $team = Team::factory()->create(['name' => $name]);
            $season->teams()->attach($team);
            $group->teams()->attach($team);
            $teams[$name] = $team;
        }
    }

    $play = function (string $home, string $away, int $hs, int $as) use ($groupStage, $teams): void {
        $group = Group::query()
            ->where('stage_id', $groupStage->id)
            ->whereHas('teams', fn ($q) => $q->where('teams.id', $teams[$home]->id))
            ->firstOrFail();

        $game = Game::factory()->create([
            'stage_id' => $groupStage->id,
            'season_id' => $groupStage->season_id,
            'group_id' => $group->id,
            'home_team_id' => $teams[$home]->id,
            'away_team_id' => $teams[$away]->id,
            'status' => GameStatus::FullTime,
            'match_date' => now()->subDay(),
        ]);

        Result::factory()->create([
            'game_id' => $game->id,
            'home_team_score' => $hs,
            'away_team_score' => $as,
        ]);
    };

    $play('Alpha One', 'Alpha Two', 2, 0);
    $play('Alpha One', 'Alpha Three', 1, 0);
    $play('Alpha Two', 'Alpha Three', 1, 0);

    $play('Beta One', 'Beta Two', 1, 0);
    $play('Beta One', 'Beta Three', 1, 0);
    $play('Beta Two', 'Beta Three', 4, 0);

    $knockout = Stage::factory()->singleElimination()->create([
        'season_id' => $season->id,
        'name' => 'Knockout',
        'order' => 20,
        'config' => ['entrants' => [
            ['type' => 'group', 'group' => 'Group A', 'position' => 1],
            ['type' => 'best_placed', 'rank' => 2],
            ['type' => 'group', 'group' => 'Group B', 'position' => 1],
            ['type' => 'best_placed', 'rank' => 1],
        ]],
    ]);

    app(GenerateFixtures::class)->execute($knockout->fresh('season.teams'));

    return [$league, $season, $groupStage, $knockout, $teams];
}

function roundOneGames(Stage $knockout)
{
    return $knockout->games()->where('round', 1)->orderBy('bracket_position')->get();
}

describe('SeedStageFromGroups', function () {
    it('fills round-1 games from group positions and best-placed ranks', function () {
        [, , , $knockout, $teams] = seededKnockoutScaffold();

        app(SeedStageFromGroups::class)->execute($knockout);

        $games = roundOneGames($knockout);

        // Allocation keeps thirds out of their own group: Alpha One (Group A
        // winner) faces Beta Two, not the Group-A third Alpha Two — which the
        // naive rank-based placement would have produced as a rematch.
        expect($games[0]->home_team_id)->toBe($teams['Alpha One']->id);
        expect($games[0]->away_team_id)->toBe($teams['Beta Two']->id);
        expect($games[1]->home_team_id)->toBe($teams['Beta One']->id);
        expect($games[1]->away_team_id)->toBe($teams['Alpha Two']->id);
    });

    it('previews the resolution without writing anything', function () {
        [, , , $knockout, $teams] = seededKnockoutScaffold();

        $preview = app(SeedStageFromGroups::class)->preview($knockout);

        expect($preview['source']['name'])->toBe('Groups');
        expect($preview['source_complete'])->toBeTrue();
        expect($preview['slots'][0]['label'])->toBe('Winner Group A');
        expect($preview['slots'][0]['team']['name'])->toBe('Alpha One');
        // Best-placed slots are a pooled, allocation-filled set: labelled by
        // the placed position (advances_count 1 → 2nd) and tagged with the
        // group each allocated team actually came from.
        expect($preview['slots'][1]['label'])->toBe('Best 2nd-placed');
        expect($preview['slots'][1]['team']['name'])->toBe('Beta Two');
        expect($preview['slots'][1]['origin_group'])->toBe('Group B');
        expect($preview['slots'][1]['rematch'])->toBeFalse();
        expect($preview['slots'][3]['team']['name'])->toBe('Alpha Two');

        expect(roundOneGames($knockout)->every(fn (Game $game) => $game->home_team_id === null))->toBeTrue();
    });

    it('re-applies cleanly after a result correction changes a qualifier', function () {
        [, , , $knockout, $teams] = seededKnockoutScaffold();

        $action = app(SeedStageFromGroups::class);
        $action->execute($knockout);

        // Alpha Two's win over Alpha Three becomes a 5-0 statement… and
        // Alpha One's 2-0 over Alpha Two is corrected to a 0-3 loss, making
        // Alpha Two group winner.
        Game::query()
            ->where('home_team_id', $teams['Alpha One']->id)
            ->where('away_team_id', $teams['Alpha Two']->id)
            ->firstOrFail()
            ->result()
            ->update(['home_team_score' => 0, 'away_team_score' => 3]);

        $action->execute($knockout->fresh());

        // Alpha Two now wins Group A; Alpha One drops to the third-placed pool
        // and is allocated away from the Group-A slot, so match 1 is
        // Alpha Two v Beta Two.
        expect(roundOneGames($knockout)[0]->home_team_id)->toBe($teams['Alpha Two']->id);
        expect(roundOneGames($knockout)[0]->away_team_id)->toBe($teams['Beta Two']->id);
    });

    it('refuses to re-seed once a round-1 game has started', function () {
        [, , , $knockout] = seededKnockoutScaffold();

        $action = app(SeedStageFromGroups::class);
        $action->execute($knockout);

        roundOneGames($knockout)->first()->update(['status' => GameStatus::Live]);

        $action->execute($knockout->fresh());
    })->throws(DomainException::class, 'already started');

    it('reports unresolvable slots in the preview and refuses to execute', function () {
        [, , , $knockout] = seededKnockoutScaffold();

        $knockout->update(['config' => ['entrants' => [
            ['type' => 'group', 'group' => 'Group Z', 'position' => 1],
            ['type' => 'group', 'group' => 'Group A', 'position' => 1],
        ]]]);

        $preview = app(SeedStageFromGroups::class)->preview($knockout->fresh());

        expect($preview['slots'][0]['team'])->toBeNull();
        expect($preview['slots'][0]['error'])->toContain('Group Z');

        expect(fn () => app(SeedStageFromGroups::class)->execute($knockout->fresh()))
            ->toThrow(DomainException::class, 'Group Z');
    });

    it('throws when no earlier grouped stage exists', function () {
        $season = Season::factory()->create();
        $knockout = Stage::factory()->singleElimination()->create([
            'season_id' => $season->id,
            'config' => ['entrants' => [
                ['type' => 'best_placed', 'rank' => 1],
                ['type' => 'best_placed', 'rank' => 2],
            ]],
        ]);

        app(SeedStageFromGroups::class)->preview($knockout);
    })->throws(DomainException::class, 'no earlier grouped stage');
});

describe('Seeding endpoint and page payload', function () {
    it('shows the resolved seeding to the stage owner', function () {
        [$league, $season, , $knockout] = seededKnockoutScaffold();

        $this->actingAs($league->owner)
            ->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$knockout->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('seeding.source.name', 'Groups')
                ->where('seeding.source_complete', true)
                ->where('seeding.seeded', false)
                ->where('seeding.can_apply', true)
                ->where('seeding.slots.0.team.name', 'Alpha One')
            );
    });

    it('hides the seeding payload from guests', function () {
        [$league, $season, , $knockout] = seededKnockoutScaffold();

        $this->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$knockout->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('seeding', null));
    });

    it('seeds the bracket when the owner confirms', function () {
        [$league, $season, , $knockout, $teams] = seededKnockoutScaffold();

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$knockout->id}/seed-from-groups")
            ->assertRedirect();

        expect(roundOneGames($knockout)[0]->home_team_id)->toBe($teams['Alpha One']->id);
    });

    it('forbids non-owners from seeding', function () {
        [$league, $season, , $knockout] = seededKnockoutScaffold();

        $this->actingAs(User::factory()->create())
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$knockout->id}/seed-from-groups")
            ->assertForbidden();
    });
});
