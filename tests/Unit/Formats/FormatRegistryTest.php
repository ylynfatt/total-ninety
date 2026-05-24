<?php

use App\Domain\Formats\FormatRegistry;
use App\Domain\Formats\RoundRobinDoubleGenerator;
use App\Domain\Formats\RoundRobinSingleGenerator;
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

    it('throws DomainException for formats not yet registered', function (StageFormat $format) {
        $registry = app(FormatRegistry::class);

        expect(fn () => $registry->for($format))
            ->toThrow(DomainException::class);
    })->with([
        'group stage' => StageFormat::GroupStage,
        'single elimination' => StageFormat::SingleElimination,
        'double elimination' => StageFormat::DoubleElimination,
        'conference' => StageFormat::Conference,
    ]);

    it('reports supports() correctly for registered and unregistered formats', function () {
        $registry = app(FormatRegistry::class);

        expect($registry->supports(StageFormat::RoundRobinSingle))->toBeTrue();
        expect($registry->supports(StageFormat::RoundRobinDouble))->toBeTrue();
        expect($registry->supports(StageFormat::GroupStage))->toBeFalse();
        expect($registry->supports(StageFormat::SingleElimination))->toBeFalse();
    });
});
