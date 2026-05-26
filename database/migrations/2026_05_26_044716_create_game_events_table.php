<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Team;
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
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Game::class)->constrained()->cascadeOnDelete();

            // Match-clock minute. Stoppage minutes are tracked separately so
            // a 45+2 event is (minute=45, stoppage=2) and orders correctly
            // alongside a 46th-minute event from the second half.
            $table->unsignedSmallInteger('minute');
            $table->unsignedSmallInteger('stoppage')->nullable();

            // String column backed by the GameEventType enum on the model.
            $table->string('type');

            // For most events: the team the event belongs to (the scoring
            // team, the booked team, the subbing team). Null for neutral
            // events (kickoff, half time, full time, var check, generic
            // commentary).
            $table->foreignIdFor(Team::class)->nullable()->constrained()->nullOnDelete();

            // Primary actor. For a goal: the scorer. For a card: the booked
            // player. For a sub: the player coming OFF (the on player is
            // secondary_player_id).
            $table->foreignIdFor(Player::class)->nullable()->constrained()->nullOnDelete();

            // Goal assists.
            $table->foreignId('assist_player_id')
                ->nullable()
                ->constrained('players')
                ->nullOnDelete();

            // The other half of a substitution: the player coming ON.
            $table->foreignId('secondary_player_id')
                ->nullable()
                ->constrained('players')
                ->nullOnDelete();

            $table->string('description')->nullable();
            $table->timestamps();

            // Timeline reads — Game::events() orders by minute then id.
            $table->index(['game_id', 'minute', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
