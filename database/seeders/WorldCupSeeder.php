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
 * is the founding year of each nation's football association.
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
            // Hosts
            ['name' => 'Canada', 'acronym' => 'CAN', 'year_founded' => 1912],
            ['name' => 'Mexico', 'acronym' => 'MEX', 'year_founded' => 1927],
            ['name' => 'United States', 'acronym' => 'USA', 'year_founded' => 1913],

            // UEFA
            ['name' => 'France', 'acronym' => 'FRA', 'year_founded' => 1919],
            ['name' => 'England', 'acronym' => 'ENG', 'year_founded' => 1863],
            ['name' => 'Spain', 'acronym' => 'ESP', 'year_founded' => 1909],
            ['name' => 'Portugal', 'acronym' => 'POR', 'year_founded' => 1914],
            ['name' => 'Netherlands', 'acronym' => 'NED', 'year_founded' => 1889],
            ['name' => 'Germany', 'acronym' => 'GER', 'year_founded' => 1900],
            ['name' => 'Italy', 'acronym' => 'ITA', 'year_founded' => 1898],
            ['name' => 'Belgium', 'acronym' => 'BEL', 'year_founded' => 1895],
            ['name' => 'Croatia', 'acronym' => 'CRO', 'year_founded' => 1912],
            ['name' => 'Switzerland', 'acronym' => 'SUI', 'year_founded' => 1895],
            ['name' => 'Denmark', 'acronym' => 'DEN', 'year_founded' => 1889],
            ['name' => 'Austria', 'acronym' => 'AUT', 'year_founded' => 1904],
            ['name' => 'Poland', 'acronym' => 'POL', 'year_founded' => 1919],
            ['name' => 'Norway', 'acronym' => 'NOR', 'year_founded' => 1902],
            ['name' => 'Turkey', 'acronym' => 'TUR', 'year_founded' => 1923],
            ['name' => 'Serbia', 'acronym' => 'SRB', 'year_founded' => 1919],

            // CONMEBOL
            ['name' => 'Brazil', 'acronym' => 'BRA', 'year_founded' => 1914],
            ['name' => 'Argentina', 'acronym' => 'ARG', 'year_founded' => 1893],
            ['name' => 'Uruguay', 'acronym' => 'URU', 'year_founded' => 1900],
            ['name' => 'Colombia', 'acronym' => 'COL', 'year_founded' => 1924],
            ['name' => 'Ecuador', 'acronym' => 'ECU', 'year_founded' => 1925],
            ['name' => 'Paraguay', 'acronym' => 'PAR', 'year_founded' => 1906],

            // CAF
            ['name' => 'Morocco', 'acronym' => 'MAR', 'year_founded' => 1955],
            ['name' => 'Senegal', 'acronym' => 'SEN', 'year_founded' => 1960],
            ['name' => 'Egypt', 'acronym' => 'EGY', 'year_founded' => 1921],
            ['name' => 'Nigeria', 'acronym' => 'NGA', 'year_founded' => 1945],
            ['name' => 'Algeria', 'acronym' => 'ALG', 'year_founded' => 1962],
            ['name' => 'Ivory Coast', 'acronym' => 'CIV', 'year_founded' => 1960],
            ['name' => 'Cameroon', 'acronym' => 'CMR', 'year_founded' => 1959],
            ['name' => 'Ghana', 'acronym' => 'GHA', 'year_founded' => 1957],
            ['name' => 'Tunisia', 'acronym' => 'TUN', 'year_founded' => 1957],

            // AFC
            ['name' => 'Japan', 'acronym' => 'JPN', 'year_founded' => 1921],
            ['name' => 'South Korea', 'acronym' => 'KOR', 'year_founded' => 1933],
            ['name' => 'Iran', 'acronym' => 'IRN', 'year_founded' => 1920],
            ['name' => 'Australia', 'acronym' => 'AUS', 'year_founded' => 1961],
            ['name' => 'Saudi Arabia', 'acronym' => 'KSA', 'year_founded' => 1956],
            ['name' => 'Qatar', 'acronym' => 'QAT', 'year_founded' => 1960],
            ['name' => 'Uzbekistan', 'acronym' => 'UZB', 'year_founded' => 1946],
            ['name' => 'Jordan', 'acronym' => 'JOR', 'year_founded' => 1949],

            // CONCACAF
            ['name' => 'Costa Rica', 'acronym' => 'CRC', 'year_founded' => 1921],
            ['name' => 'Panama', 'acronym' => 'PAN', 'year_founded' => 1937],
            ['name' => 'Jamaica', 'acronym' => 'JAM', 'year_founded' => 1910],

            // OFC
            ['name' => 'New Zealand', 'acronym' => 'NZL', 'year_founded' => 1891],

            // Inter-confederation play-off berths
            ['name' => 'Iraq', 'acronym' => 'IRQ', 'year_founded' => 1948],
            ['name' => 'DR Congo', 'acronym' => 'COD', 'year_founded' => 1919],
        ];
    }
}
