<?php

use App\Enums\StageFormat;
use App\Models\Game;
use App\Models\Group;
use App\Models\Season;
use App\Models\Stage;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

describe('StageFormat enum', function () {
    it('classifies table vs bracket formats', function () {
        expect(StageFormat::RoundRobinSingle->isTable())->toBeTrue();
        expect(StageFormat::RoundRobinDouble->isTable())->toBeTrue();
        expect(StageFormat::GroupStage->isTable())->toBeTrue();
        expect(StageFormat::Conference->isTable())->toBeTrue();

        expect(StageFormat::SingleElimination->isBracket())->toBeTrue();
        expect(StageFormat::DoubleElimination->isBracket())->toBeTrue();
    });

    it('only GroupStage and Conference are grouped formats', function () {
        expect(StageFormat::GroupStage->hasGroups())->toBeTrue();
        expect(StageFormat::Conference->hasGroups())->toBeTrue();

        expect(StageFormat::RoundRobinSingle->hasGroups())->toBeFalse();
        expect(StageFormat::SingleElimination->hasGroups())->toBeFalse();
    });

    it('provides human-friendly labels for every case', function () {
        foreach (StageFormat::cases() as $format) {
            expect($format->label())->toBeString()->not->toBe('');
        }
    });
});

describe('Stage relationships', function () {
    it('belongs to a season and casts the format to StageFormat enum', function () {
        $stage = Stage::factory()->create(['format' => StageFormat::RoundRobinDouble]);

        expect($stage->season())->toBeInstanceOf(BelongsTo::class);
        expect($stage->season)->toBeInstanceOf(Season::class);
        expect($stage->format)->toBe(StageFormat::RoundRobinDouble);
    });

    it('is exposed via Season::stages() ordered by `order`', function () {
        $season = Season::factory()->create();
        $third = Stage::factory()->create(['season_id' => $season->id, 'order' => 30]);
        $first = Stage::factory()->create(['season_id' => $season->id, 'order' => 10]);
        $second = Stage::factory()->create(['season_id' => $season->id, 'order' => 20]);

        expect($season->stages())->toBeInstanceOf(HasMany::class);
        expect($season->stages->pluck('id')->all())->toBe([$first->id, $second->id, $third->id]);
    });

    it('cascade-deletes stages when its season is deleted', function () {
        $season = Season::factory()->create();
        $stage = Stage::factory()->create(['season_id' => $season->id]);

        $season->delete();

        expect(Stage::find($stage->id))->toBeNull();
    });

    it('enforces a unique (season_id, name) pair', function () {
        $season = Season::factory()->create();
        Stage::factory()->create(['season_id' => $season->id, 'name' => 'Regular Season']);

        expect(fn () => Stage::factory()->create(['season_id' => $season->id, 'name' => 'Regular Season']))
            ->toThrow(QueryException::class);
    });

    it('round-trips the config JSON column as an array', function () {
        $stage = Stage::factory()->create([
            'config' => ['points_per_win' => 3, 'tiebreakers' => ['gd', 'gf']],
        ]);

        expect($stage->fresh()->config)->toBe([
            'points_per_win' => 3,
            'tiebreakers' => ['gd', 'gf'],
        ]);
    });
});

describe('Group relationships', function () {
    it('belongs to a stage', function () {
        $group = Group::factory()->create();

        expect($group->stage())->toBeInstanceOf(BelongsTo::class);
        expect($group->stage)->toBeInstanceOf(Stage::class);
    });

    it('is exposed via Stage::groups() ordered by `order`', function () {
        $stage = Stage::factory()->groupStage()->create();
        $second = Group::factory()->create(['stage_id' => $stage->id, 'order' => 20]);
        $first = Group::factory()->create(['stage_id' => $stage->id, 'order' => 10]);

        expect($stage->groups())->toBeInstanceOf(HasMany::class);
        expect($stage->groups->pluck('id')->all())->toBe([$first->id, $second->id]);
    });

    it('cascade-deletes groups when its stage is deleted', function () {
        $stage = Stage::factory()->groupStage()->create();
        $group = Group::factory()->create(['stage_id' => $stage->id]);

        $stage->delete();

        expect(Group::find($group->id))->toBeNull();
    });
});

describe('group_team pivot', function () {
    it('attaches teams to a group', function () {
        $group = Group::factory()->create();
        $teams = Team::factory()->count(4)->create();

        $group->teams()->attach($teams);

        expect($group->teams())->toBeInstanceOf(BelongsToMany::class);
        expect($group->teams)->toHaveCount(4);
    });

    it('enforces a unique (group_id, team_id) pair', function () {
        $group = Group::factory()->create();
        $team = Team::factory()->create();
        $group->teams()->attach($team);

        expect(fn () => $group->teams()->attach($team))
            ->toThrow(QueryException::class);
    });

    it('cascade-deletes pivot rows when a group is deleted', function () {
        $group = Group::factory()->create();
        $team = Team::factory()->create();
        $group->teams()->attach($team);

        $group->delete();

        expect(DB::table('group_team')->where('team_id', $team->id)->count())->toBe(0);
    });
});

describe('Game ↔ Stage / Group', function () {
    it('allows games without a stage or group (legacy compat)', function () {
        $game = Game::factory()->create();

        expect($game->stage_id)->toBeNull();
        expect($game->group_id)->toBeNull();
    });

    it('associates a game with its stage', function () {
        $stage = Stage::factory()->create();
        $game = Game::factory()->create(['stage_id' => $stage->id]);

        expect($game->stage)->toBeInstanceOf(Stage::class);
        expect($stage->games)->toHaveCount(1);
    });

    it('associates a game with its group', function () {
        $stage = Stage::factory()->groupStage()->create();
        $group = Group::factory()->create(['stage_id' => $stage->id]);
        $game = Game::factory()->create(['stage_id' => $stage->id, 'group_id' => $group->id]);

        expect($game->group)->toBeInstanceOf(Group::class);
        expect($group->games)->toHaveCount(1);
    });

    it('nullifies group_id when its group is deleted (does not delete the game)', function () {
        $stage = Stage::factory()->groupStage()->create();
        $group = Group::factory()->create(['stage_id' => $stage->id]);
        $game = Game::factory()->create(['stage_id' => $stage->id, 'group_id' => $group->id]);

        $group->delete();

        $fresh = $game->fresh();
        expect($fresh)->not->toBeNull();
        expect($fresh->group_id)->toBeNull();
    });

    it('cascade-deletes games when their stage is deleted', function () {
        $stage = Stage::factory()->create();
        $game = Game::factory()->create(['stage_id' => $stage->id]);

        $stage->delete();

        expect(Game::find($game->id))->toBeNull();
    });
});
