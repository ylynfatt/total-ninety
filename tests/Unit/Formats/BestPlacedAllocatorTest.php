<?php

use App\Domain\Formats\BestPlacedAllocator;

/**
 * Shorthand team array. Group defaults to the name's first letter so
 * "Ecuador"@E reads naturally in the expectations.
 *
 * @return array{id: int, name: string, acronym: string, group: string}
 */
function team(int $id, string $name, string $group): array
{
    return ['id' => $id, 'name' => $name, 'acronym' => strtoupper(substr($name, 0, 3)), 'group' => $group];
}

/**
 * @return array{index: int, opponent_group: string|null}
 */
function slot(int $index, ?string $opponentGroup): array
{
    return ['index' => $index, 'opponent_group' => $opponentGroup];
}

describe('BestPlacedAllocator', function () {
    it('assigns teams to slots without drawing a team against its own group', function () {
        $teams = [
            team(1, 'Alpha', 'Group A'),
            team(2, 'Bravo', 'Group B'),
        ];

        // Slot 1 faces Group A, slot 3 faces Group B — so Alpha must go to
        // slot 3 and Bravo to slot 1, the opposite of rank order.
        $slots = [slot(1, 'Group A'), slot(3, 'Group B')];

        $allocation = (new BestPlacedAllocator)->allocate($teams, $slots);

        expect($allocation[1]['team']['name'])->toBe('Bravo');
        expect($allocation[1]['rematch'])->toBeFalse();
        expect($allocation[3]['team']['name'])->toBe('Alpha');
        expect($allocation[3]['rematch'])->toBeFalse();
    });

    it('keeps best rank in the earliest slot when no constraint forces otherwise', function () {
        $teams = [
            team(1, 'Alpha', 'Group A'),
            team(2, 'Bravo', 'Group B'),
        ];

        // Neither slot faces A or B, so rank order stands: Alpha first.
        $slots = [slot(0, 'Group C'), slot(2, 'Group D')];

        $allocation = (new BestPlacedAllocator)->allocate($teams, $slots);

        expect($allocation[0]['team']['name'])->toBe('Alpha');
        expect($allocation[2]['team']['name'])->toBe('Bravo');
    });

    it('resolves a four-team combination avoiding every same-group clash', function () {
        $teams = [
            team(1, 'Alpha', 'Group A'),
            team(2, 'Bravo', 'Group B'),
            team(3, 'Charlie', 'Group C'),
            team(4, 'Delta', 'Group D'),
        ];

        $slots = [slot(0, 'Group A'), slot(1, 'Group B'), slot(2, 'Group C'), slot(3, 'Group D')];

        $allocation = (new BestPlacedAllocator)->allocate($teams, $slots);

        foreach ($slots as $s) {
            expect($allocation[$s['index']]['team']['group'])->not->toBe($s['opponent_group']);
            expect($allocation[$s['index']]['rematch'])->toBeFalse();
        }

        // Every team placed exactly once.
        $names = collect($allocation)->pluck('team.name');
        expect($names->unique())->toHaveCount(4);
    });

    it('ignores slots with no known opponent group', function () {
        $teams = [team(1, 'Alpha', 'Group A'), team(2, 'Bravo', 'Group B')];
        $slots = [slot(0, null), slot(1, null)];

        $allocation = (new BestPlacedAllocator)->allocate($teams, $slots);

        expect($allocation[0]['team']['name'])->toBe('Alpha');
        expect($allocation[0]['rematch'])->toBeFalse();
        expect($allocation[1]['team']['name'])->toBe('Bravo');
    });

    it('flags an unavoidable rematch instead of failing', function () {
        // Two teams from Group A, and both slots face Group A — no layout
        // avoids a rematch, so one is placed and flagged.
        $teams = [team(1, 'Alpha', 'Group A'), team(2, 'Alpha II', 'Group A')];
        $slots = [slot(0, 'Group A'), slot(1, 'Group B')];

        $allocation = (new BestPlacedAllocator)->allocate($teams, $slots);

        expect($allocation[0]['rematch'])->toBeTrue();
        expect($allocation[1]['rematch'])->toBeFalse();
        // Both teams still placed.
        expect(collect($allocation)->pluck('team.id')->unique())->toHaveCount(2);
    });

    it('is deterministic across runs', function () {
        $teams = [
            team(1, 'Alpha', 'Group A'),
            team(2, 'Bravo', 'Group B'),
            team(3, 'Charlie', 'Group C'),
            team(4, 'Delta', 'Group D'),
        ];
        $slots = [slot(0, 'Group D'), slot(1, 'Group A'), slot(2, 'Group B'), slot(3, 'Group C')];

        $allocator = new BestPlacedAllocator;
        $first = $allocator->allocate($teams, $slots);
        $second = $allocator->allocate($teams, $slots);

        expect(collect($first)->map(fn ($a) => $a['team']['id'])->all())
            ->toBe(collect($second)->map(fn ($a) => $a['team']['id'])->all());
    });
});
