<?php

use App\Actions\BuildDashboardOverview;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\League;
use App\Models\Result;
use App\Models\Season;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia;

/** @param array<string, mixed> $attributes */
function dashboardGame(Stage $stage, array $attributes = []): Game
{
    return Game::factory()->for($stage)->for($stage->season)->create($attributes);
}

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 8)->setTime(12, 0));
    $this->withHeaders([
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'overview',
    ]);
});

it('provides an empty overview to a new league owner', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('overview.summary', ['live' => 0, 'today' => 0, 'attention' => 0])
            ->has('overview.leagues', 0)
            ->has('overview.matchday.live', 0)
            ->has('overview.matchday.upcoming', 0)
            ->has('overview.matchday.finished', 0)
            ->has('overview.matchday.other', 0)
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
            ->where('overview.summary.live', 2)
            ->where('overview.summary.today', 5)
            ->has('overview.matchday.live', 2)
            ->where('overview.matchday.live', fn ($matches): bool => collect($matches)->pluck('id')->sort()->values()->all() === collect([$live->id, $halfTime->id])->sort()->values()->all())
            ->has('overview.matchday.upcoming', 2)
            ->where('overview.matchday.upcoming.0.id', $early->id)
            ->where('overview.matchday.upcoming.1.id', $late->id)
            ->has('overview.matchday.finished', 1)
            ->where('overview.matchday.finished.0.id', $finished->id)
            ->where('overview.matchday.finished.0.homeScore', 2)
            ->where('overview.matchday.finished.0.awayScore', 0)
            ->where('overview.matchday.finished.0.manageUrl', route('fixtures.edit', [$league, $season, $stage, $finished]))
            ->where('overview.matchday.finished.0.viewUrl', route('games.show', [$league, $season, $stage, $finished]))
            ->has('overview.matchday.other', 2)
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
            ->where('overview.summary', ['live' => 1, 'today' => 1, 'attention' => 0])
            ->has('overview.leagues', 1)
            ->where('overview.leagues.0.id', $league->id)
            ->has('overview.matchday.live', 1)
            ->where('overview.matchday.live.0.id', $ownGame->id)
            ->where('overview.attention.0.count', 0)
            ->where('overview.attention.1.count', 0)
            ->where('overview.attention.2.count', 0)
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
            ->where('overview.summary.attention', 3)
            ->where('overview.attention.0.count', 1)
            ->where('overview.attention.0.items.0.id', $overdue->id)
            ->where('overview.attention.1.count', 1)
            ->where('overview.attention.1.items.0.id', $unscheduled->id)
            ->where('overview.attention.1.items.0.href', route('fixtures.edit', [$league, $season, $stage, $unscheduled]))
            ->where('overview.attention.2.count', 1)
            ->where('overview.attention.2.items.0.id', $emptyStage->id)
            ->where('overview.attention.2.items.0.href', route('stages.show', [$league, $season, $emptyStage]))
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
    $leagues = collect($response->inertiaProps('overview.leagues'))->keyBy('id');
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
            ->where('overview.summary.attention', 7)
            ->where('overview.attention.0.count', 7)
            ->has('overview.attention.0.items', 5)
        );
});

it('renders the dashboard shell without querying overview data', function (): void {
    $this->mock(BuildDashboardOverview::class)->shouldNotReceive('execute');

    $this->actingAs(User::factory()->create())
        ->withoutHeader('X-Inertia-Partial-Data')
        ->withoutHeader('X-Inertia-Partial-Component')
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('dateLabel')
            ->has('timezone')
            ->missing('overview')
        );
});

it('returns a request error when overview data fails and allows a successful retry', function (): void {
    Exceptions::fake();
    $user = User::factory()->create();
    $this->mock(BuildDashboardOverview::class)->shouldReceive('execute')->once()
        ->andThrow(new RuntimeException('Overview temporarily unavailable'));

    $this->actingAs($user)->get(route('dashboard'))->assertServerError();
    Exceptions::assertReported(RuntimeException::class);

    $this->app->forgetInstance(BuildDashboardOverview::class);

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.summary', ['live' => 0, 'today' => 0, 'attention' => 0])
            ->has('overview.leagues', 0)
        );
});

it('distinguishes leagues with no active season from active seasons with no fixtures', function (): void {
    $league = League::factory()->create();
    Season::factory()->for($league)->create();
    $activeLeague = League::factory()->for($league->owner, 'owner')->create();
    $season = Season::factory()->active()->for($activeLeague)->create();

    $response = $this->actingAs($league->owner)->get(route('dashboard'))->assertSuccessful();
    $leagues = collect($response->inertiaProps('overview.leagues'))->keyBy('id');

    expect($leagues[$league->id]['seasons'])->toBeEmpty()
        ->and($leagues[$activeLeague->id]['seasons'])->toHaveCount(1)
        ->and($leagues[$activeLeague->id]['seasons'][0]['id'])->toBe($season->id)
        ->and($leagues[$activeLeague->id]['seasons'][0]['games'])->toBe(0)
        ->and($leagues[$activeLeague->id]['seasons'][0]['nextFixture'])->toBeNull();
});
