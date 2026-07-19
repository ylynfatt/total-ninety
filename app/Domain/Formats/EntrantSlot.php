<?php

namespace App\Domain\Formats;

use App\Models\Stage;
use DomainException;

/**
 * One entry slot in a knockout bracket, describing where its team comes
 * from rather than who the team is. Stored on the knockout stage as
 * stage.config['entrants'] — an ordered list where consecutive pairs
 * (0,1), (2,3)… are the round-1 matchups, mirroring the generator's
 * bracket_position layout so slot 2p is game p's home side and 2p+1 its
 * away side.
 *
 * Two shapes:
 *   ['type' => 'group', 'group' => 'Group A', 'position' => 1]
 *     — the team finishing at `position` in the named group of the
 *       previous stage (1 = winner, 2 = runner-up, …).
 *   ['type' => 'best_placed', 'rank' => 2]
 *     — the rank-th team in the cross-group best-placed ranking (the
 *       BestPlacedCalculator table). Which concrete slot each best-placed
 *       team fills is refined by the allocation step when seeding.
 */
final readonly class EntrantSlot
{
    private function __construct(
        public string $type,
        public ?string $group,
        public ?int $position,
        public ?int $rank,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $type = $raw['type'] ?? null;

        if ($type === 'group') {
            $group = $raw['group'] ?? null;
            $position = $raw['position'] ?? null;

            if (! is_string($group) || $group === '' || ! is_numeric($position) || (int) $position < 1) {
                throw new DomainException('A group entrant slot needs a group name and a position of at least 1.');
            }

            return new self('group', $group, (int) $position, null);
        }

        if ($type === 'best_placed') {
            $rank = $raw['rank'] ?? null;

            if (! is_numeric($rank) || (int) $rank < 1) {
                throw new DomainException('A best-placed entrant slot needs a rank of at least 1.');
            }

            return new self('best_placed', null, null, (int) $rank);
        }

        throw new DomainException("Unknown entrant slot type [{$type}]; expected 'group' or 'best_placed'.");
    }

    /**
     * The stage's configured entrant slots, in bracket order. Empty array
     * when the stage has none configured.
     *
     * @return array<int, self>
     */
    public static function listForStage(Stage $stage): array
    {
        $entrants = $stage->config['entrants'] ?? null;

        if (! is_array($entrants) || $entrants === []) {
            return [];
        }

        return array_map(
            fn (array $raw): self => self::fromArray($raw),
            array_values($entrants),
        );
    }

    /**
     * Human label for an unfilled bracket slot, e.g. "Winner Group A",
     * "Runner-up Group B", "3rd Group C", "Best-placed #4".
     */
    public function label(): string
    {
        if ($this->type === 'best_placed') {
            return "Best-placed #{$this->rank}";
        }

        return match ($this->position) {
            1 => "Winner {$this->group}",
            2 => "Runner-up {$this->group}",
            default => self::ordinal((int) $this->position)." {$this->group}",
        };
    }

    /**
     * @return array{type: string, group?: string, position?: int, rank?: int}
     */
    public function toArray(): array
    {
        if ($this->type === 'best_placed') {
            return ['type' => 'best_placed', 'rank' => (int) $this->rank];
        }

        return ['type' => 'group', 'group' => (string) $this->group, 'position' => (int) $this->position];
    }

    private static function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n.$suffix;
    }
}
