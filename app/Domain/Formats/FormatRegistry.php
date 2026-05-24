<?php

namespace App\Domain\Formats;

use App\Enums\StageFormat;
use DomainException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the correct FixtureGenerator implementation for a given
 * StageFormat. Backed by the service container so generators with
 * dependencies (like RoundRobinDoubleGenerator depending on
 * RoundRobinSingleGenerator) are autowired.
 *
 * Phase 3b only ships generators for the two round-robin formats.
 * The remaining four formats (group stage, single/double elimination,
 * conference) get registered when Phase 3c lands; until then, calling
 * for() with one of those formats throws DomainException so callers
 * fail fast with a clear message instead of silently producing zero
 * fixtures.
 */
class FormatRegistry
{
    /**
     * @var array<string, class-string<FixtureGenerator>>
     */
    private array $generators = [
        // Populated below in the constructor.
    ];

    public function __construct(private readonly Container $container)
    {
        $this->generators = [
            StageFormat::RoundRobinSingle->value => RoundRobinSingleGenerator::class,
            StageFormat::RoundRobinDouble->value => RoundRobinDoubleGenerator::class,
        ];
    }

    public function for(StageFormat $format): FixtureGenerator
    {
        if (! isset($this->generators[$format->value])) {
            throw new DomainException(
                "No fixture generator is registered for stage format [{$format->value}]."
            );
        }

        return $this->container->make($this->generators[$format->value]);
    }

    /**
     * Whether a generator is registered for the given format.
     */
    public function supports(StageFormat $format): bool
    {
        return isset($this->generators[$format->value]);
    }
}
