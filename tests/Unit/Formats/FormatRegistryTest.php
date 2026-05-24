<?php

use App\Domain\Formats\FormatRegistry;
use App\Domain\Formats\GroupStageGenerator;
use App\Domain\Formats\RoundRobinDoubleGenerator;
use App\Domain\Formats\RoundRobinSingleGenerator;
use App\Domain\Formats\SingleEliminationGenerator;
use App\Enums\StageFormat;

describe('FormatRegistry', function () {
    it('resolves a RoundRobinSingleGenerator for RoundRobinSingle', function () {
        $registry = app(FormatRegistry::class);

        expect($registry->for(StageFormat::RoundRobinSingle))
            ->toBeInstanceOf(RoundRobinSingleGenerator::class);
    });

    it('resolves a RoundRobinDoubleGenerator for RoundRobinDouble', function () {
        $registry = app(FormatRegistry::class);

        expect($registry->for(StageFormat::RoundRobinDouble))
            ->toBeInstanceOf(RoundRobinDoubleGenerator::class);
    });

    it('resolves a GroupStageGenerator for GroupStage', function () {
        $registry = app(FormatRegistry::class);

        expect($registry->for(StageFormat::GroupStage))
            ->toBeInstanceOf(GroupStageGenerator::class);
    });

    it('resolves a SingleEliminationGenerator for SingleElimination', function () {
        $registry = app(FormatRegistry::class);

        expect($registry->for(StageFormat::SingleElimination))
            ->toBeInstanceOf(SingleEliminationGenerator::class);
    });

    it('throws DomainException for formats not yet registered', function (StageFormat $format) {
        $registry = app(FormatRegistry::class);

        expect(fn () => $registry->for($format))
            ->toThrow(DomainException::class);
    })->with([
        'double elimination' => StageFormat::DoubleElimination,
        'conference' => StageFormat::Conference,
    ]);

    it('reports supports() correctly for registered and unregistered formats', function () {
        $registry = app(FormatRegistry::class);

        expect($registry->supports(StageFormat::RoundRobinSingle))->toBeTrue();
        expect($registry->supports(StageFormat::RoundRobinDouble))->toBeTrue();
        expect($registry->supports(StageFormat::GroupStage))->toBeTrue();
        expect($registry->supports(StageFormat::SingleElimination))->toBeTrue();
        expect($registry->supports(StageFormat::Conference))->toBeFalse();
    });
});
