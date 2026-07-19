<?php

use App\Actions\GenerateFixtures;
use App\Enums\StageFormat;
use App\Models\Group;
use App\Models\League;
use App\Models\Season;
use App\Models\Stage;

/**
 * Build a public league + season with a completed-shape group stage (2 groups)
 * and a knockout stage after it. Returns [league, season, groupStage, knockout].
 *
 * @param  array<string, mixed>  $knockoutAttributes
 * @return array{0: League, 1: Season, 2: Stage, 3: Stage}
 */
function knockoutScaffold(array $knockoutAttributes = []): array
{
    $league = League::factory()->create(['is_public' => true]);
    $season = Season::factory()->create(['league_id' => $league->id]);

    $groupStage = Stage::factory()->groupStage()->create([
        'season_id' => $season->id,
        'name' => 'Groups',
        'order' => 10,
        'advances_count' => 2,
    ]);

    Group::factory()->create(['stage_id' => $groupStage->id, 'name' => 'Group A', 'order' => 0]);
    Group::factory()->create(['stage_id' => $groupStage->id, 'name' => 'Group B', 'order' => 1]);

    $knockout = Stage::factory()->singleElimination()->create([
        'season_id' => $season->id,
        'name' => 'Knockout',
        'order' => 20,
        ...$knockoutAttributes,
    ]);

    return [$league, $season, $groupStage, $knockout];
}

/**
 * The classic 2-group entrant template: 1A v 2B, 1B v 2A.
 *
 * @return array<int, array<string, mixed>>
 */
function classicFourEntrants(): array
{
    return [
        ['type' => 'group', 'group' => 'Group A', 'position' => 1],
        ['type' => 'group', 'group' => 'Group B', 'position' => 2],
        ['type' => 'group', 'group' => 'Group B', 'position' => 1],
        ['type' => 'group', 'group' => 'Group A', 'position' => 2],
    ];
}

describe('Stage config.entrants validation', function () {
    it('accepts a valid entrant list on a knockout stage', function () {
        [$league, $season, , $knockout] = knockoutScaffold();

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$knockout->id}", [
                'name' => $knockout->name,
                'config' => ['entrants' => classicFourEntrants()],
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        expect($knockout->fresh()->config['entrants'])->toBe(classicFourEntrants());
    });

    it('rejects an entrant count that is not a power of two', function () {
        [$league, $season, , $knockout] = knockoutScaffold();

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$knockout->id}", [
                'name' => $knockout->name,
                'config' => ['entrants' => array_slice(classicFourEntrants(), 0, 3)],
            ])
            ->assertSessionHasErrors('config.entrants');
    });

    it('rejects malformed slot descriptors', function () {
        [$league, $season, , $knockout] = knockoutScaffold();

        $this->actingAs($league->owner)
            ->put("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$knockout->id}", [
                'name' => $knockout->name,
                'config' => ['entrants' => [
                    ['type' => 'group', 'group' => 'Group A', 'position' => 1],
                    ['type' => 'wildcard'],
                ]],
            ])
            ->assertSessionHasErrors('config.entrants');
    });

    it('silently drops entrants for non-bracket formats', function () {
        $league = League::factory()->create();
        $season = Season::factory()->create(['league_id' => $league->id]);

        $this->actingAs($league->owner)
            ->post("/leagues/{$league->slug}/seasons/{$season->id}/stages", [
                'name' => 'Groups',
                'format' => StageFormat::GroupStage->value,
                'config' => ['entrants' => classicFourEntrants()],
            ])
            ->assertRedirect();

        expect(Stage::where('name', 'Groups')->firstOrFail()->config)->toBeNull();
    });
});

describe('Entrant-driven fixture generation and bracket placeholders', function () {
    it('generates an all-TBD bracket from the entrant list', function () {
        [, , , $knockout] = knockoutScaffold([
            'config' => ['entrants' => classicFourEntrants()],
        ]);

        $games = app(GenerateFixtures::class)->execute($knockout->fresh('season.teams'));

        expect($games)->toHaveCount(3);
        expect($games->every(fn ($game) => $game->home_team_id === null && $game->away_team_id === null))->toBeTrue();
    });

    it('labels unfilled round-1 slots with their entrant descriptors', function () {
        [$league, $season, , $knockout] = knockoutScaffold([
            'config' => ['entrants' => classicFourEntrants()],
        ]);

        app(GenerateFixtures::class)->execute($knockout->fresh('season.teams'));

        $this->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$knockout->id}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('bracket.0.games.0.home_placeholder', 'Winner Group A')
                ->where('bracket.0.games.0.away_placeholder', 'Runner-up Group B')
                ->where('bracket.0.games.1.home_placeholder', 'Winner Group B')
                ->where('bracket.0.games.1.away_placeholder', 'Runner-up Group A')
                ->where('bracket.1.games.0.home_placeholder', null)
                ->where('bracket.1.games.0.away_placeholder', null)
            );
    });
});

describe('Stage edit sourceStage prop', function () {
    it('exposes the previous grouped stage to the entrant builder', function () {
        [$league, $season, $groupStage, $knockout] = knockoutScaffold();

        $groupStage->update(['config' => ['best_placed_count' => 2]]);

        $this->actingAs($league->owner)
            ->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$knockout->id}/edit")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('sourceStage.id', $groupStage->id)
                ->where('sourceStage.advances_count', 2)
                ->where('sourceStage.best_placed_count', 2)
                ->where('sourceStage.groups.0.name', 'Group A')
                ->where('sourceStage.groups.1.name', 'Group B')
            );
    });

    it('is null for non-bracket stages and when no grouped stage precedes', function () {
        [$league, $season, $groupStage] = knockoutScaffold();

        $this->actingAs($league->owner)
            ->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$groupStage->id}/edit")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('sourceStage', null));

        $earlyKnockout = Stage::factory()->singleElimination()->create([
            'season_id' => $season->id,
            'name' => 'Play-in',
            'order' => 5,
        ]);

        $this->actingAs($league->owner)
            ->get("/leagues/{$league->slug}/seasons/{$season->id}/stages/{$earlyKnockout->id}/edit")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('sourceStage', null));
    });
});
