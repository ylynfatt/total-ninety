<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teams = [
            [
                'name' => 'Manchester United',
                'acronym' => 'MUN',
                'home_ground' => 'Old Trafford',
                'year_founded' => 1878,
            ],
            [
                'name' => 'Arsenal',
                'acronym' => 'ARS',
                'home_ground' => 'Emirates Stadium',
                'year_founded' => 1886,
            ],
            [
                'name' => 'Manchester City',
                'acronym' => 'MCI',
                'home_ground' => 'Etihad Stadium',
                'year_founded' => 1880,
            ],
            [
                'name' => 'Liverpool',
                'acronym' => 'LIV',
                'home_ground' => 'Anfield',
                'year_founded' => 1892,
            ],
            [
                'name' => 'Chelsea',
                'acronym' => 'CHE',
                'home_ground' => 'Stamford Bridge',
                'year_founded' => 1905,
            ],
            [
                'name' => 'Tottenham',
                'acronym' => 'TOT',
                'home_ground' => 'Tottenham Hotspur Stadium',
                'year_founded' => 1886,
            ],
            [
                'name' => 'Leeds',
                'acronym' => 'LEE',
                'home_ground' => 'Elland Road',
                'year_founded' => 1919,
            ],
            [
                'name' => 'Everton',
                'acronym' => 'EVE',
                'home_ground' => 'Goodison Park',
                'year_founded' => 1878,
            ],
            [
                'name' => 'West Ham United',
                'acronym' => 'WHU',
                'home_ground' => 'London Stadium',
                'year_founded' => 1895,
            ],
            [
                'name' => 'Aston Villa',
                'acronym' => 'AVL',
                'home_ground' => 'Villa Park',
                'year_founded' => 1874,
            ],
            [
                'name' => 'Burnley',
                'acronym' => 'BUR',
                'home_ground' => 'Turf Moor',
                'year_founded' => 1882,
            ],
            [
                'name' => 'Crystal Palace',
                'acronym' => 'CRY',
                'home_ground' => 'Selhurst Park',
                'year_founded' => 1905,
            ],
            [
                'name' => 'Wolverhampton Wanderers',
                'acronym' => 'WOL',
                'home_ground' => 'Molineux Stadium',
                'year_founded' => 1877,
            ],
            [
                'name' => 'Leeds United',
                'acronym' => 'LEE',
                'home_ground' => 'Elland Road',
                'year_founded' => 1919,
            ],
            [
                'name' => 'Newcastle United',
                'acronym' => 'NEW',
                'home_ground' => "St James' Park",
                'year_founded' => 1892,
            ],
            [
                'name' => 'Brighton & Hove Albion',
                'acronym' => 'BHA',
                'home_ground' => 'Falmer Stadium',
                'year_founded' => 1901,
            ],
            [
                'name' => 'Fulham',
                'acronym' => 'FUL',
                'home_ground' => 'Craven Cottage',
                'year_founded' => 1879,
            ],
            [
                'name' => 'Nottingham Forrest',
                'acronym' => 'NOT',
                'home_ground' => 'City Ground',
                'year_founded' => 1865,
            ],
            [
                'name' => 'Bournemouth',
                'acronym' => 'BOU',
                'home_ground' => 'Vitality Stadium',
                'year_founded' => 1899,
            ],
            [
                'name' => 'Brentford',
                'acronym' => 'BRE',
                'home_ground' => 'Brentford Community Stadium',
                'year_founded' => 1889,
            ],
            [
                'name' => 'Sunderland',
                'acronym' => 'SUN',
                'home_ground' => 'Stadium of Light',
                'year_founded' => 1879,
            ],
        ];

        DB::table('teams')->insert($teams);
    }
}
