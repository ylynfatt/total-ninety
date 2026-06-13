<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Seeds the national teams contesting the 2026 FIFA World Cup — the first
 * 48-team edition, co-hosted by Canada, Mexico and the United States.
 *
 * Acronyms are the official FIFA country codes. `home_ground` is left null
 * since national sides don't have a single club venue, and `year_founded`
 * is the founding year of each nation's football association. Teams are
 * grouped by qualifying confederation (UEFA 16, CONMEBOL 6, CAF 10, AFC 9,
 * CONCACAF 6, OFC 1).
 */
class WorldCupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->teams() as $team) {
            Team::query()->updateOrCreate(
                ['acronym' => $team['acronym']],
                $team,
            );
        }
    }

    /**
     * @return array<int, array{name: string, acronym: string, year_founded: int}>
     */
    private function teams(): array
    {
        return [
            // UEFA (16)
            ['name' => 'Austria', 'acronym' => 'AUT', 'year_founded' => 1904],
            ['name' => 'Belgium', 'acronym' => 'BEL', 'year_founded' => 1895],
            ['name' => 'Bosnia and Herzegovina', 'acronym' => 'BIH', 'year_founded' => 1992],
            ['name' => 'Croatia', 'acronym' => 'CRO', 'year_founded' => 1912],
            ['name' => 'Czech Republic', 'acronym' => 'CZE', 'year_founded' => 1901],
            ['name' => 'England', 'acronym' => 'ENG', 'year_founded' => 1863],
            ['name' => 'France', 'acronym' => 'FRA', 'year_founded' => 1919],
            ['name' => 'Germany', 'acronym' => 'GER', 'year_founded' => 1900],
            ['name' => 'Netherlands', 'acronym' => 'NED', 'year_founded' => 1889],
            ['name' => 'Norway', 'acronym' => 'NOR', 'year_founded' => 1902],
            ['name' => 'Portugal', 'acronym' => 'POR', 'year_founded' => 1914],
            ['name' => 'Scotland', 'acronym' => 'SCO', 'year_founded' => 1873],
            ['name' => 'Spain', 'acronym' => 'ESP', 'year_founded' => 1909],
            ['name' => 'Sweden', 'acronym' => 'SWE', 'year_founded' => 1904],
            ['name' => 'Switzerland', 'acronym' => 'SUI', 'year_founded' => 1895],
            ['name' => 'Turkey', 'acronym' => 'TUR', 'year_founded' => 1923],

            // CONMEBOL (6)
            ['name' => 'Argentina', 'acronym' => 'ARG', 'year_founded' => 1893],
            ['name' => 'Brazil', 'acronym' => 'BRA', 'year_founded' => 1914],
            ['name' => 'Colombia', 'acronym' => 'COL', 'year_founded' => 1924],
            ['name' => 'Ecuador', 'acronym' => 'ECU', 'year_founded' => 1925],
            ['name' => 'Paraguay', 'acronym' => 'PAR', 'year_founded' => 1906],
            ['name' => 'Uruguay', 'acronym' => 'URU', 'year_founded' => 1900],

            // CAF (10)
            ['name' => 'Algeria', 'acronym' => 'ALG', 'year_founded' => 1962],
            ['name' => 'Cape Verde', 'acronym' => 'CPV', 'year_founded' => 1982],
            ['name' => 'DR Congo', 'acronym' => 'COD', 'year_founded' => 1919],
            ['name' => 'Egypt', 'acronym' => 'EGY', 'year_founded' => 1921],
            ['name' => 'Ghana', 'acronym' => 'GHA', 'year_founded' => 1957],
            ['name' => 'Ivory Coast', 'acronym' => 'CIV', 'year_founded' => 1960],
            ['name' => 'Morocco', 'acronym' => 'MAR', 'year_founded' => 1955],
            ['name' => 'Senegal', 'acronym' => 'SEN', 'year_founded' => 1960],
            ['name' => 'South Africa', 'acronym' => 'RSA', 'year_founded' => 1991],
            ['name' => 'Tunisia', 'acronym' => 'TUN', 'year_founded' => 1957],

            // AFC (9)
            ['name' => 'Australia', 'acronym' => 'AUS', 'year_founded' => 1961],
            ['name' => 'Iran', 'acronym' => 'IRN', 'year_founded' => 1920],
            ['name' => 'Iraq', 'acronym' => 'IRQ', 'year_founded' => 1948],
            ['name' => 'Japan', 'acronym' => 'JPN', 'year_founded' => 1921],
            ['name' => 'Jordan', 'acronym' => 'JOR', 'year_founded' => 1949],
            ['name' => 'Qatar', 'acronym' => 'QAT', 'year_founded' => 1960],
            ['name' => 'Saudi Arabia', 'acronym' => 'KSA', 'year_founded' => 1956],
            ['name' => 'South Korea', 'acronym' => 'KOR', 'year_founded' => 1933],
            ['name' => 'Uzbekistan', 'acronym' => 'UZB', 'year_founded' => 1946],

            // CONCACAF (6) — includes hosts Canada, Mexico and the United States
            ['name' => 'Canada', 'acronym' => 'CAN', 'year_founded' => 1912],
            ['name' => 'Curaçao', 'acronym' => 'CUW', 'year_founded' => 1921],
            ['name' => 'Haiti', 'acronym' => 'HAI', 'year_founded' => 1904],
            ['name' => 'Mexico', 'acronym' => 'MEX', 'year_founded' => 1927],
            ['name' => 'Panama', 'acronym' => 'PAN', 'year_founded' => 1937],
            ['name' => 'United States', 'acronym' => 'USA', 'year_founded' => 1913],

            // OFC (1)
            ['name' => 'New Zealand', 'acronym' => 'NZL', 'year_founded' => 1891],
        ];
    }
}
