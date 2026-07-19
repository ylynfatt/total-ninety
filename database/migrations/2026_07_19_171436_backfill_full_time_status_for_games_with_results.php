<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Historically, recording a result through the manual editor left the
     * game's status untouched, so played games could sit at scheduled/
     * postponed forever — invisible to everything keyed off a final status
     * (bracket advancement, stage-completeness checks). The controller now
     * marks such games Full Time at entry; this backfills the ones already
     * recorded.
     *
     * Plain query-builder update on purpose: no model events, so the
     * backfill doesn't fire broadcasts or bracket advancement mid-migration.
     */
    public function up(): void
    {
        DB::table('games')
            ->whereIn('status', ['scheduled', 'postponed'])
            ->whereExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('results')
                ->whereColumn('results.game_id', 'games.id'))
            ->update(['status' => 'full_time']);
    }

    /**
     * Irreversible: there is no record of which games were backfilled
     * versus completed normally.
     */
    public function down(): void
    {
        //
    }
};
