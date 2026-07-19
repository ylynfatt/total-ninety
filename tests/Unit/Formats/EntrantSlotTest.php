<?php

use App\Domain\Formats\EntrantSlot;

describe('EntrantSlot', function () {
    it('labels group slots by finishing position', function (int $position, string $expected) {
        $slot = EntrantSlot::fromArray(['type' => 'group', 'group' => 'Group A', 'position' => $position]);

        expect($slot->label())->toBe($expected);
    })->with([
        'winner' => [1, 'Winner Group A'],
        'runner-up' => [2, 'Runner-up Group A'],
        'third' => [3, '3rd Group A'],
        'fourth' => [4, '4th Group A'],
    ]);

    it('labels best-placed slots by rank', function () {
        $slot = EntrantSlot::fromArray(['type' => 'best_placed', 'rank' => 4]);

        expect($slot->label())->toBe('Best-placed #4');
    });

    it('normalizes string numbers and drops extraneous keys in toArray', function () {
        $slot = EntrantSlot::fromArray(['type' => 'group', 'group' => 'Group B', 'position' => '2', 'junk' => true]);

        expect($slot->toArray())->toBe(['type' => 'group', 'group' => 'Group B', 'position' => 2]);
    });

    it('rejects malformed slots', function (array $raw) {
        EntrantSlot::fromArray($raw);
    })->with([
        'unknown type' => [['type' => 'wildcard', 'rank' => 1]],
        'group without name' => [['type' => 'group', 'position' => 1]],
        'group position zero' => [['type' => 'group', 'group' => 'Group A', 'position' => 0]],
        'best placed without rank' => [['type' => 'best_placed']],
        'best placed rank zero' => [['type' => 'best_placed', 'rank' => 0]],
    ])->throws(DomainException::class);
});
