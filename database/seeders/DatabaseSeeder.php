<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Team::factory()->create([
            'name' => 'Manchester United',
            'acronym' => 'MUN',
            'home_ground' => 'Old Trafford',
            'year_founded' => 1878,
        ]);

        Team::factory()->create([
            'name' => 'Arsenal',
            'acronym' => 'ARS',
            'home_ground' => 'Emirates Stadium',
            'year_founded' => 1886,
        ]);

        Team::factory()->create([
            'name' => 'Manchester City',
            'acronym' => 'MCI',
            'home_ground' => 'Etihad Stadium',
            'year_founded' => 1880,
        ]);

        Team::factory()->create([
            'name' => 'Liverpool',
            'acronym' => 'LIV',
            'home_ground' => 'Anfield',
            'year_founded' => 1892,
        ]);

        Team::factory()->create([
            'name' => 'Chelsea',
            'acronym' => 'CHE',
            'home_ground' => 'Stamford Bridge',
            'year_founded' => 1905,
        ]);

        Team::factory()->create([
            'name' => 'Tottenham',
            'acronym' => 'TOT',
            'home_ground' => 'Tottenham Hotspur Stadium',
            'year_founded' => 1886,
        ]);
    }
}
