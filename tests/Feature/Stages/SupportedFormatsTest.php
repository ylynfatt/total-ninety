<?php

use App\Domain\Formats\FormatRegistry;
use App\Enums\StageFormat;
use App\Models\Game;
use App\Models\Season;
use App\Models\Stage;

it('offers only formats with registered fixture generators', function (): void {
    $season = Season::factory()->create();
    $league = $season->league;
    $expected = array_map(fn (StageFormat $format): string => $format->value, app(FormatRegistry::class)->supportedFormats());

    $this->actingAs($league->owner)->get(route('stages.create', [$league, $season]))
        ->assertInertia(fn ($page) => $page->where('formats', fn ($formats): bool => collect($formats)->pluck('value')->all() === $expected));
    expect($expected)->not->toContain('double_elimination');
    $this->post(route('stages.store', [$league, $season]), ['name' => 'Unsupported', 'format' => 'double_elimination'])
        ->assertSessionHasErrors('format');
    expect($season->stages()->count())->toBe(0);
});

it('lets an empty legacy unsupported stage switch to a supported format', function (): void {
    $stage = Stage::factory()->create(['format' => StageFormat::DoubleElimination]);
    $season = $stage->season;
    $league = $season->league;

    $this->actingAs($league->owner)->get(route('stages.edit', [$league, $season, $stage]))
        ->assertSuccessful()->assertInertia(fn ($page) => $page->where('canChangeFormat', true));
    $this->put(route('stages.update', [$league, $season, $stage]), ['name' => $stage->name, 'format' => 'single_elimination'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($stage->fresh()->format)->toBe(StageFormat::SingleElimination);
});

it('keeps formats locked when changing them could invalidate existing data', function (): void {
    $stage = Stage::factory()->create(['format' => StageFormat::DoubleElimination]);
    $season = $stage->season;
    $league = $season->league;
    Game::factory()->for($stage)->for($season)->create();

    $this->actingAs($league->owner)->get(route('stages.show', [$league, $season, $stage]))->assertSuccessful();
    $this->get(route('stages.edit', [$league, $season, $stage]))
        ->assertInertia(fn ($page) => $page->where('canChangeFormat', false));
    $this->put(route('stages.update', [$league, $season, $stage]), ['name' => $stage->name, 'format' => 'single_elimination'])
        ->assertSessionHasErrors('format');
    expect($stage->fresh()->format)->toBe(StageFormat::DoubleElimination);
});
