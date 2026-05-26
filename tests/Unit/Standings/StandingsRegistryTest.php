<?php

use App\Domain\Standings\RoundRobinStandingsCalculator;
use App\Domain\Standings\StandingsRegistry;
use App\Enums\StageFormat;

describe('StandingsRegistry', function () {
    it('resolves a RoundRobinStandingsCalculator for every table format', function (StageFormat $format) {
        $registry = app(StandingsRegistry::class);

        expect($registry->for($format))->toBeInstanceOf(RoundRobinStandingsCalculator::class);
    })->with([
        'RoundRobinSingle' => StageFormat::RoundRobinSingle,
        'RoundRobinDouble' => StageFormat::RoundRobinDouble,
        'GroupStage' => StageFormat::GroupStage,
        'Conference' => StageFormat::Conference,
    ]);

    it('throws DomainException for bracket formats', function (StageFormat $format) {
        $registry = app(StandingsRegistry::class);

        expect(fn () => $registry->for($format))
            ->toThrow(DomainException::class);
    })->with([
        'SingleElimination' => StageFormat::SingleElimination,
        'DoubleElimination' => StageFormat::DoubleElimination,
    ]);

    it('reports supports() correctly across the format set', function () {
        $registry = app(StandingsRegistry::class);

        expect($registry->supports(StageFormat::RoundRobinSingle))->toBeTrue();
        expect($registry->supports(StageFormat::RoundRobinDouble))->toBeTrue();
        expect($registry->supports(StageFormat::GroupStage))->toBeTrue();
        expect($registry->supports(StageFormat::Conference))->toBeTrue();

        expect($registry->supports(StageFormat::SingleElimination))->toBeFalse();
        expect($registry->supports(StageFormat::DoubleElimination))->toBeFalse();
    });
});
