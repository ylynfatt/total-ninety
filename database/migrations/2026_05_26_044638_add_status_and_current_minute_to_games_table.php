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
            // Game lifecycle: scheduled → live → half_time → live → full_time
            // (postponed/cancelled as off-ramps). String column backed by the
            // GameStatus enum on the model.
            $table->string('status')->default('scheduled')->after('location');

            // The "minute" clock the scoreboard shows during a live game.
            // Nullable because non-live games don't have a meaningful value.
            // Stoppage minutes ride on the GameEvent's separate stoppage column
            // rather than encoding into a single integer here.
            $table->unsignedSmallInteger('current_minute')->nullable()->after('status');

            // Indexed so the scoreboard can quickly filter all live games
            // across the whole league.
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'current_minute']);
        });
    }
};
