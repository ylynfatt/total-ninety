<?php

use App\Models\Team;
use Database\Seeders\WorldCupSeeder;

test('it seeds the 48 world cup national teams', function () {
    $this->seed(WorldCupSeeder::class);

    expect(Team::query()->count())->toBe(48);
    expect(Team::query()->where('name', 'Brazil')->where('acronym', 'BRA')->exists())->toBeTrue();
    expect(Team::query()->whereIn('acronym', ['CAN', 'MEX', 'USA'])->count())->toBe(3);
});

test('it is idempotent and does not duplicate teams when run twice', function () {
    $this->seed(WorldCupSeeder::class);
    $this->seed(WorldCupSeeder::class);

    expect(Team::query()->count())->toBe(48);
});

test('it uses unique fifa country codes for every team', function () {
    $this->seed(WorldCupSeeder::class);

    expect(Team::query()->distinct()->count('acronym'))->toBe(48);
});
