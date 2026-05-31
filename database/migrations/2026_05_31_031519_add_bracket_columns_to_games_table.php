<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Knockout bracket coordinates. round 1 is the opening round; the
            // final is the highest round. bracket_position is the 0-based slot
            // within a round, so winner of round r positions 2p and 2p+1 feed
            // round r+1 position p. Null for non-bracket formats.
            $table->unsignedSmallInteger('round')->nullable()->after('group_id');
            $table->unsignedSmallInteger('bracket_position')->nullable()->after('round');

            $table->index(['stage_id', 'round', 'bracket_position']);
        });

        // Bracket placeholder games for later rounds have no teams yet (TBD),
        // so the team FKs must allow nulls.
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('home_team_id')->nullable()->change();
            $table->foreignId('away_team_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['stage_id', 'round', 'bracket_position']);
            $table->dropColumn(['round', 'bracket_position']);
        });
    }
};
