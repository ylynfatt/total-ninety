<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\League;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/** @param array<string, mixed> $attributes */
function dashboardGame(Stage $stage, array $attributes = []): Game
{
    return Game::factory()->for($stage)->for($stage->season)->create($attributes);
}

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 8)->setTime(12, 0));
});

it('provides an empty overview to a new league owner', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('summary', ['live' => 0, 'today' => 0, 'attention' => 0])
            ->has('leagues', 0)
            ->has('matchday.live', 0)
            ->has('matchday.upcoming', 0)
            ->has('matchday.finished', 0)
            ->has('matchday.other', 0)
        );
});

it('groups todays matches and includes earlier or undated matches still in progress', function (): void {
    $league = League::factory()->create();
    $season = Season::factory()->active()->for($league)->create();
    $stage = Stage::factory()->for($season)->create();
    $early = dashboardGame($stage, ['match_date' => today()]);
    $late = dashboardGame($stage, ['match_date' => today()->endOfDay()]);
    $live = dashboardGame($stage, ['match_date' => today()->subDay(), 'status' => GameStatus::Live]);
    $halfTime = dashboardGame($stage, ['match_date' => null, 'status' => GameStatus::HalfTime]);
    $finished = dashboardGame($stage, ['match_date' => today()->addHours(10), 'status' => GameStatus::FullTime]);
    Result::factory()->for($finished)->create(['home_team_score' => 2, 'away_team_score' => 0]);
    dashboardGame($stage, ['match_date' => today()->addHours(9), 'status' => GameStatus::Cancelled]);
    dashboardGame($stage, ['match_date' => today()->addHours(11), 'status' => GameStatus::Postponed]);
    dashboardGame($stage, ['match_date' => today()->addDay()]);

    $this->actingAs($league->owner)->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.live', 2)
            ->where('summary.today', 5)
            ->has('matchday.live', 2)
            ->where('matchday.live', fn ($matches): bool => collect($matches)->pluck('id')->sort()->values()->all() === collect([$live->id, $halfTime->id])->sort()->values()->all())
            ->has('matchday.upcoming', 2)
            ->where('matchday.upcoming.0.id', $early->id)
            ->where('matchday.upcoming.1.id', $late->id)
            ->has('matchday.finished', 1)
            ->where('matchday.finished.0.id', $finished->id)
            ->where('matchday.finished.0.homeScore', 2)
            ->where('matchday.finished.0.awayScore', 0)
            ->where('matchday.finished.0.manageUrl', route('fixtures.edit', [$league, $season, $stage, $finished]))
            ->where('matchday.finished.0.viewUrl', route('games.show', [$league, $season, $stage, $finished]))
            ->has('matchday.other', 2)
        );
});

it('keeps every dashboard section scoped to the signed in users leagues', function (): void {
    $league = League::factory()->private()->create();
    $ownSeason = Season::factory()->active()->for($league)->create();
    $ownStage = Stage::factory()->for($ownSeason)->create();
    $ownGame = dashboardGame($ownStage, ['status' => GameStatus::Live, 'match_date' => today()->addHours(10)]);

    foreach ([true, false] as $public) {
        $otherLeague = League::factory()->create(['is_public' => $public]);
        $otherSeason = Season::factory()->active()->for($otherLeague)->create();
        $otherStage = Stage::factory()->for($otherSeason)->create();
        dashboardGame($otherStage, ['status' => GameStatus::Live, 'match_date' => today()]);
        dashboardGame($otherStage, ['match_date' => today()->subDay()]);
        dashboardGame($otherStage, ['match_date' => null]);
        Stage::factory()->for($otherSeason)->create();
    }

    Game::factory()->live()->create(['match_date' => today()]);

    $this->actingAs($league->owner)->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary', ['live' => 1, 'today' => 1, 'attention' => 0])
            ->has('leagues', 1)
            ->where('leagues.0.id', $league->id)
            ->has('matchday.live', 1)
            ->where('matchday.live.0.id', $ownGame->id)
            ->where('attention.0.count', 0)
            ->where('attention.1.count', 0)
            ->where('attention.2.count', 0)
        );
});

it('flags actionable active season tasks without treating bracket placeholders as unscheduled fixtures', function (): void {
    $league = League::factory()->create();
    $season = Season::factory()->active()->for($league)->create();
    $stage = Stage::factory()->for($season)->create();
    $overdue = dashboardGame($stage, ['match_date' => today()->subDay()]);
    $unscheduled = dashboardGame($stage, ['match_date' => null]);
    $emptyStage = Stage::factory()->for($season)->create();
    dashboardGame($stage, ['match_date' => null, 'home_team_id' => null, 'away_team_id' => null]);
    dashboardGame($stage, ['match_date' => null, 'away_team_id' => null]);
    dashboardGame($stage, ['match_date' => today()->addHours(1)]);
    $recorded = dashboardGame($stage, ['match_date' => today()->subDays(2)]);
    Result::factory()->for($recorded)->create();

    foreach ([GameStatus::Postponed, GameStatus::Cancelled, GameStatus::FullTime, GameStatus::Live, GameStatus::HalfTime] as $status) {
        dashboardGame($stage, ['match_date' => today()->subDays(2), 'status' => $status]);
    }

    $inactiveSeason = Season::factory()->for($league)->create();
    $inactiveStage = Stage::factory()->for($inactiveSeason)->create();
    dashboardGame($inactiveStage, ['match_date' => null]);
    dashboardGame($inactiveStage, ['match_date' => today()->subDay()]);
    Stage::factory()->for($inactiveSeason)->create();

    $this->actingAs($league->owner)->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.attention', 3)
            ->where('attention.0.count', 1)
            ->where('attention.0.items.0.id', $overdue->id)
            ->where('attention.1.count', 1)
            ->where('attention.1.items.0.id', $unscheduled->id)
            ->where('attention.1.items.0.href', route('fixtures.edit', [$league, $season, $stage, $unscheduled]))
            ->where('attention.2.count', 1)
            ->where('attention.2.items.0.id', $emptyStage->id)
            ->where('attention.2.items.0.href', route('stages.show', [$league, $season, $emptyStage]))
        );
});

it('shows active season completion and the next scheduled fixture for each league', function (): void {
    $league = League::factory()->create();
    $season = Season::factory()->active()->for($league)->create();
    $stage = Stage::factory()->for($season)->create();
    dashboardGame($stage, ['status' => GameStatus::FullTime]);
    dashboardGame($stage, ['match_date' => now()->subHour()]);
    $next = dashboardGame($stage, ['match_date' => now()->addHours(2)]);
    dashboardGame($stage, ['match_date' => now()->addDay()]);
    Season::factory()->for($league)->create();
    $secondLeague = League::factory()->for($league->owner, 'owner')->create();
    $secondSeason = Season::factory()->active()->for($secondLeague)->create();
    $secondStage = Stage::factory()->for($secondSeason)->create();
    $secondNext = dashboardGame($secondStage, ['match_date' => now()->addHours(3)]);

    $response = $this->actingAs($league->owner)->get(route('dashboard'))->assertSuccessful();
    $leagues = collect($response->inertiaProps('leagues'))->keyBy('id');
    $seasonData = $leagues[$league->id]['seasons'][0];

    expect($leagues)->toHaveCount(2)
        ->and($leagues[$league->id]['seasons'])->toHaveCount(1)
        ->and($seasonData['games'])->toBe(4)
        ->and($seasonData['completed'])->toBe(1)
        ->and($seasonData['nextFixture']['homeTeam'])->toBe($next->homeTeam->name)
        ->and($seasonData['nextFixture']['kickoff'])->toBe($next->match_date->toISOString())
        ->and($leagues[$secondLeague->id]['seasons'][0]['nextFixture']['homeTeam'])->toBe($secondNext->homeTeam->name);
});

it('limits attention previews while retaining accurate totals', function (): void {
    $league = League::factory()->create();
    $season = Season::factory()->active()->for($league)->create();
    $stage = Stage::factory()->for($season)->create();
    Game::factory()->count(7)->for($stage)->for($season)->create(['match_date' => today()->subDay()]);

    $this->actingAs($league->owner)->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.attention', 7)
            ->where('attention.0.count', 7)
            ->has('attention.0.items', 5)
        );
});
