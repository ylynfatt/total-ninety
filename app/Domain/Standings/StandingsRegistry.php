<?php

namespace App\Domain\Standings;

use App\Enums\StageFormat;
use DomainException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the correct StandingsCalculator for a given StageFormat.
 *
 * The four "table" formats — RoundRobinSingle, RoundRobinDouble,
 * GroupStage, Conference — all share the same calculator
 * (RoundRobinStandingsCalculator). The differences between them live in
 * the FixtureGenerator layer; once games + results exist, the math is
 * identical regardless of how the schedule was generated.
 *
 * The two bracket formats — SingleElimination, DoubleElimination — don't
 * produce a standings table at all. The registry throws DomainException
 * for them so callers fail fast with a clear message. (Bracket rendering
 * will live in a parallel registry / calculator if we add one later.)
 */
class StandingsRegistry
{
    /**
     * @var array<string, class-string<StandingsCalculator>>
     */
    private array $calculators;

    public function __construct(private readonly Container $container)
    {
        $this->calculators = [
            StageFormat::RoundRobinSingle->value => RoundRobinStandingsCalculator::class,
            StageFormat::RoundRobinDouble->value => RoundRobinStandingsCalculator::class,
            StageFormat::GroupStage->value => RoundRobinStandingsCalculator::class,
            StageFormat::Conference->value => RoundRobinStandingsCalculator::class,
        ];
    }

    public function for(StageFormat $format): StandingsCalculator
    {
        if (! isset($this->calculators[$format->value])) {
            throw new DomainException(
                "No standings calculator is registered for stage format [{$format->value}] (bracket formats don't produce tables)."
            );
        }

        return $this->container->make($this->calculators[$format->value]);
    }

    /**
     * Whether a format produces a standings table (vs. a bracket).
     */
    public function supports(StageFormat $format): bool
    {
        return isset($this->calculators[$format->value]);
    }
}
