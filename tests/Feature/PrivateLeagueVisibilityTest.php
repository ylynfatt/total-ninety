<?php

use App\Events\GameEventRecorded;
use App\Events\GameStatusChanged;
use App\Events\ScoreUpdated;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\League;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

it('protects private gamecasts and excludes private fixtures from the public scoreboard', function (): void {
    $league = League::factory()->private()->create();
    $season = Season::factory()->for($league)->create();
    $stage = Stage::factory()->for($season)->create();
    $game = Game::factory()->live()->for($stage)->for($season)->create();
    $url = route('games.show', [$league, $season, $stage, $game]);

    $this->get($url)->assertForbidden();
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    $this->actingAs($league->owner)->get($url)->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('privateBroadcast', true)
            ->where('broadcastChannel', "game.{$game->id}.owner.{$league->user_id}"));
    $this->get(route('scoreboard.index'))->assertInertia(fn ($page) => $page->has('games', 0));
    $this->get(route('teams.show', $game->homeTeam))->assertInertia(fn ($page) => $page->has('team.seasons', 0));
});

it('uses owner-only channels for private matches and rechecks current visibility for every broadcast', function (): void {
    $league = League::factory()->create();
    $season = Season::factory()->for($league)->create();
    $stage = Stage::factory()->for($season)->create();
    $game = Game::factory()->for($stage)->for($season)->create();
    $event = GameEvent::factory()->for($game)->create();
    $score = new ScoreUpdated($game);

    expect(collect($score->broadcastOn())->pluck('name')->all())->toContain('scoreboard.live');
    $league->update(['is_public' => false]);
    $privateChannel = "private-game.{$game->id}.owner.{$league->user_id}";

    foreach ([$score, new GameStatusChanged($game), new GameEventRecorded($event)] as $broadcast) {
        expect(collect($broadcast->broadcastOn())->pluck('name')->all())->toBe([$privateChannel]);
    }

    require base_path('routes/channels.php');
    $authorize = Broadcast::getChannels()['game.{gameId}.owner.{ownerId}'];
    expect($authorize($league->owner, $game->id, $league->user_id))->toBeTrue()
        ->and($authorize(User::factory()->create(), $game->id, $league->user_id))->toBeFalse()
        ->and($authorize($league->owner, $game->id, $league->user_id + 1))->toBeFalse();
});

it('does not reveal private league memberships on public team pages', function (): void {
    $season = Season::factory()->for(League::factory()->private())->create();
    $team = Team::factory()->create();
    $season->teams()->attach($team);

    $this->get(route('teams.show', $team))->assertInertia(fn ($page) => $page->has('team.seasons', 0));
    $this->actingAs($season->league->owner)->get(route('teams.show', $team))
        ->assertInertia(fn ($page) => $page->has('team.seasons', 1));
});

it('rejects inconsistent season and stage links instead of exposing another leagues match', function (): void {
    $publicSeason = Season::factory()->create();
    $privateSeason = Season::factory()->for(League::factory()->private())->create();
    $stage = Stage::factory()->for($publicSeason)->create();
    $game = Game::factory()->live()->for($stage)->for($privateSeason)->create();

    $this->get(route('games.show', [$publicSeason->league, $publicSeason, $stage, $game]))->assertNotFound();
    $this->get(route('scoreboard.index'))->assertInertia(fn ($page) => $page->has('games', 0));
});
