<?php

use App\Domain\Formats\ConferenceGenerator;
use App\Models\Group;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Helper: build a Conference stage with N conferences of M teams each
 * and an optional config override.
 *
 * @return array{0: Stage, 1: Collection<int, Group>}
 */
function conferenceStageWith(int $conferenceCount, int $teamsPerConference, ?array $config = null): array
{
    $season = Season::factory()->create();
    $stage = Stage::factory()->conference()->create([
        'season_id' => $season->id,
        'config' => $config,
    ]);

    $conferences = collect();
    for ($c = 0; $c < $conferenceCount; $c++) {
        $conf = Group::factory()->create(['stage_id' => $stage->id]);
        $conf->teams()->attach(Team::factory()->count($teamsPerConference)->create());
        $conferences->push($conf);
    }

    return [$stage, $conferences];
}

describe('ConferenceGenerator', function () {
    it('produces 2 × n(n-1)/2 intra-conference games per conference by default', function () {
        // Default config: intra=2 (home+away), cross=0
        // 2 conferences of 4 teams → 2 * (4*3/2) = 12 per conference × 2 = 24
        [$stage] = conferenceStageWith(conferenceCount: 2, teamsPerConference: 4);

        $pairs = (new ConferenceGenerator)->generate($stage);

        expect($pairs)->toHaveCount(24);
    });

    it('produces single-leg intra-conference games when configured', function () {
        // intra=1, cross=0 → just n(n-1)/2 per conference
        [$stage] = conferenceStageWith(
            conferenceCount: 2,
            teamsPerConference: 4,
            config: ['intra_conference_legs' => 1, 'cross_conference_legs' => 0],
        );

        $pairs = (new ConferenceGenerator)->generate($stage);

        // 4*3/2 = 6 per conference × 2 = 12
        expect($pairs)->toHaveCount(12);
    });

    it('adds cross-conference legs when configured', function () {
        // 2 conferences of 3 teams, intra=1, cross=1
        // Intra: 3*2/2 = 3 per conf × 2 = 6
        // Cross: 3 × 3 = 9 (each team in conf A plays each in conf B once)
        // Total: 15
        [$stage] = conferenceStageWith(
            conferenceCount: 2,
            teamsPerConference: 3,
            config: ['intra_conference_legs' => 1, 'cross_conference_legs' => 1],
        );

        $pairs = (new ConferenceGenerator)->generate($stage);

        expect($pairs)->toHaveCount(15);
    });

    it('tags every intra-conference pair with its conference id', function () {
        [$stage, $conferences] = conferenceStageWith(
            conferenceCount: 2,
            teamsPerConference: 3,
            config: ['intra_conference_legs' => 1, 'cross_conference_legs' => 0],
        );

        $pairs = (new ConferenceGenerator)->generate($stage);

        $teamToConference = collect();
        foreach ($conferences as $conf) {
            foreach ($conf->teams as $team) {
                $teamToConference[$team->id] = $conf->id;
            }
        }

        foreach ($pairs as $pair) {
            expect($teamToConference[$pair['home_team_id']])->toBe($pair['group_id']);
            expect($teamToConference[$pair['away_team_id']])->toBe($pair['group_id']);
        }
    });

    it('throws DomainException when fewer than 2 conferences are defined', function () {
        [$stage] = conferenceStageWith(conferenceCount: 1, teamsPerConference: 4);

        expect(fn () => (new ConferenceGenerator)->generate($stage))
            ->toThrow(DomainException::class, 'fewer than 2 conferences');
    });
});
