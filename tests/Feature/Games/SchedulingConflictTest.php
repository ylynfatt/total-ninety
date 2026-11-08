<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\League;
use App\Models\Season;
use App\Models\Stage;

/** @return array{Game, string} */
function schedulableFixture(): array
{
    $league = League::factory()->create();
    $season = Season::factory()->for($league)->create();
    $stage = Stage::factory()->for($season)->create();
    $game = Game::factory()->for($stage)->for($season)->create(['match_date' => null, 'location' => null]);
    test()->actingAs($league->owner);

    return [$game, route('fixtures.schedule.update', [$league, $season, $stage, $game])];
}

it('rejects overlapping fixtures for a team on either side', function (string $side): void {
    [$game, $url] = schedulableFixture();
    Game::factory()->create([
        $side.'_team_id' => $game->home_team_id,
        'match_date' => '2026-12-01 12:00:00', 'location' => 'Other venue',
    ]);

    $this->patch($url, ['match_date' => '2026-12-01T13:00', 'location' => 'New venue'])
        ->assertSessionHasErrors('match_date');
    expect($game->fresh()->match_date)->toBeNull();
})->with(['home', 'away']);

it('rejects overlapping venue bookings regardless of case or surrounding whitespace', function (): void {
    [$game, $url] = schedulableFixture();
    Game::factory()->create(['match_date' => '2026-12-01 12:00:00', 'location' => '  Central Stadium ']);

    $this->patch($url, ['match_date' => '2026-12-01T11:00', 'location' => 'central stadium'])
        ->assertSessionHasErrors('location');
    expect($game->fresh()->location)->toBeNull();
});

it('allows adjacent windows and excludes the fixture being edited', function (string $kickoff): void {
    [$game, $url] = schedulableFixture();
    Game::factory()->create(['home_team_id' => $game->home_team_id, 'match_date' => '2026-12-01 12:00:00', 'location' => 'Central Stadium']);

    $this->patch($url, ['match_date' => $kickoff, 'location' => 'Central Stadium'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->patch($url, ['match_date' => $kickoff, 'location' => 'Central Stadium'])
        ->assertRedirect()->assertSessionHasNoErrors();
})->with(['2026-12-01T10:00', '2026-12-01T14:00']);

it('ignores cancelled postponed and undated bookings', function (): void {
    [$game, $url] = schedulableFixture();

    foreach ([GameStatus::Cancelled, GameStatus::Postponed] as $status) {
        Game::factory()->create(['home_team_id' => $game->home_team_id, 'match_date' => '2026-12-01 12:00:00', 'location' => 'Central Stadium', 'status' => $status]);
    }

    Game::factory()->create(['home_team_id' => $game->home_team_id, 'match_date' => null, 'location' => 'Central Stadium']);
    $this->patch($url, ['match_date' => '2026-12-01T12:00', 'location' => 'Central Stadium'])
        ->assertRedirect()->assertSessionHasNoErrors();
});

it('validates a venue-only change against the existing kickoff and allows clearing it', function (): void {
    [$game, $url] = schedulableFixture();
    $game->update(['match_date' => '2026-12-01 12:00:00']);
    Game::factory()->create(['match_date' => '2026-12-01 12:00:00', 'location' => 'Booked venue']);

    $this->patch($url, ['location' => 'Booked venue'])->assertSessionHasErrors('location');
    $this->patch($url, ['match_date' => null, 'location' => null])->assertRedirect()->assertSessionHasNoErrors();
    expect($game->fresh()->match_date)->toBeNull();
});

it('normalizes offset timestamps into the configured application timezone', function (): void {
    config(['app.timezone' => 'UTC']);
    [$game, $url] = schedulableFixture();
    $this->patch($url, ['match_date' => '2026-12-01T09:00:00-04:00', 'location' => null])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($game->fresh()->match_date->format('Y-m-d H:i'))->toBe('2026-12-01 13:00');
});
